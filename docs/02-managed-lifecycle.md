# 02. Managed lifecycle: профили, selector, storage и миграции

Эта глава продолжает [первое подключение](01-instructions.md). Здесь рассматривается production-конфигурация: несколько профилей, выбор схемы серверным контекстом, кастомная ORM-таблица и обновление старых JSON payload.

Если термины subject, profile, selector и schema version пока незнакомы, сначала прочитайте главу 01.

Навигация:

- [01 — Первое подключение](01-instructions.md);
- [03 — Обновление существующего проекта](03-upgrade-notes.md).

## 1. Что делает managed layer

Низкоуровневые классы Requisites можно собирать вручную, но тогда приложение само должно соблюдать порядок операций:

- выбрать storage по профилю;
- определить selector;
- найти нужную схему и validator;
- применить payload migrations;
- сохранить только отфильтрованные данные;
- корректно обработать concurrent INSERT.

Managed layer собирает эти правила в одном месте:

```text
ArrayRequisitesProfileRegistry
├── schemas
├── validators
├── selector configuration
├── target versions + migrators
└── storage definitions
            ↓
ManagedRequisitesFactory
├── DefaultRequisitesManager
└── BackfillMigrationRunner
```

Приложение по-прежнему определяет свои схемы, form validation, ORM entities и payload migrators. Компонент отвечает за универсальный lifecycle.

## 2. Несколько профилей

Profile отвечает на вопрос «для какой задачи хранится этот JSON?». У одного subject может быть несколько независимых профилей:

```text
Company #42
├── profile=company       — банковские и юридические реквизиты
├── profile=contract      — данные для формирования договора
└── profile=marketplace   — настройки интеграции с площадкой
```

Для каждого профиля отдельно настраиваются:

- selector key и default selector;
- набор схем;
- form validation classes;
- target schema versions;
- payload migrators;
- storage.

Registry создаётся из готовых экземпляров:

```php
use PhpSoftBox\Requisites\Profile\ArrayRequisitesProfileRegistry;

$profiles = new ArrayRequisitesProfileRegistry([
    $companyProfile,
    $contractProfile,
    $marketplaceProfile,
]);
```

Порядок сохраняется. Registry сразу проверяет конфигурацию и не обращается к БД.

При сборке будут обнаружены:

- пустое имя профиля;
- повторное имя;
- пустой selector key или default selector;
- отсутствие schemas;
- несовпадение `profile/selector` внутри `RequisitesSchema` с ключами map;
- отсутствие default schema;
- отсутствующий validator;
- отсутствующая или некорректная target version.

Неизвестный профиль приводит к `ProfileNotFoundException`, а не маршрутизируется в первый зарегистрированный storage.

## 3. Selector: выбор варианта схемы

Selector отвечает на вопрос «какая разновидность схемы используется внутри профиля?». Например:

```text
profile=company
├── selector=country:RU
├── selector=country:KZ
└── selector=default
```

### Откуда берётся selector

`FallbackSelectorResolver` может читать значение из request payload или operation context.

```php
use PhpSoftBox\Requisites\Schema\SelectorResolutionPolicy;

$manager = $factory->createManager($profiles, [
    'company'    => SelectorResolutionPolicy::CONTEXT_ONLY,
    'marketplace' => SelectorResolutionPolicy::PAYLOAD_FIRST,
]);
```

Доступны четыре policy:

| Policy | Порядок выбора | Когда применять |
|---|---|---|
| `PAYLOAD_FIRST` | payload → context → default | Selector действительно выбирает пользователь |
| `CONTEXT_FIRST` | context → payload → default | Серверное значение предпочтительно, payload разрешён как fallback |
| `CONTEXT_ONLY` | context → default | Пользователь не должен выбирать чужую схему |
| `PAYLOAD_ONLY` | payload → default | Контекст не участвует в выборе |

BC default — `PAYLOAD_FIRST`.

Ключ определяется профилем:

```php
public function selectorKey(): string
{
    return 'country';
}

public function defaultSelector(): string
{
    return 'country:RU';
}
```

Тогда operation context выглядит так:

```php
$context = ['country' => 'country:KZ'];
```

Для `CONTEXT_ONLY` значение из payload игнорируется при создании записи. Если context не содержит непустой строки, используется default selector профиля.

### Новая и существующая запись ведут себя по-разному

Для transient record с `id=null` selector ещё можно определить по policy. Поэтому `validateAndSave()` может выбрать selector из payload, даже если первоначальный `load()` не получил этот payload.

Для существующей записи сохранённый `record.selector` является authoritative:

- payload не может его изменить;
- operation context не может молча переключить схему;
- совпадающее значение разрешено;
- несовпадающее значение приводит к `SelectorMismatchException`.

Это правило защищает от интерпретации старого JSON по другой, семантически несовместимой схеме.

### Как изменить selector существующей записи

Обычные `load()`, `validate()` и `validateAndSave()` этого не делают. У компонента намеренно нет неявного cross-selector migration.

Если бизнес-процесс допускает смену selector, приложение должно выполнить отдельную явную операцию и определить, что делать с payload:

- преобразовать данные специальным mapper;
- запросить новый payload у пользователя;
- полностью очистить несовместимые поля;
- запретить переход.

Обычный `PayloadMigratorInterface` предназначен для переходов между версиями одного selector и не должен использоваться для смены selector.

## 4. Operation context

Operation context передаётся последним аргументом:

```php
$record = $manager->load($subject, 'company', $context);
$schema = $manager->schema($record, $context);
$result = $manager->validateAndSave($record, $payload, $context);
```

Контекст должен содержать только данные, необходимые для текущей операции. Не передавайте в него container, request object или mutable service.

Хороший context:

```php
[
    'country' => 'country:RU',
]
```

Плохой context:

```php
[
    'request'   => $request,
    'container' => $container,
]
```

Manager не запоминает context между вызовами. Это позволяет безопасно использовать один service в long-running worker и последовательно обрабатывать разных владельцев.

## 5. Полный lifecycle manager

### `load()`

Для существующей записи:

1. registry проверяет profile;
2. profile router выбирает storage;
3. storage находит record по `subject + profile`;
4. authoritative context сверяется с сохранённым selector;
5. при необходимости выполняется lazy payload migration;
6. возвращается canonical record.

Для отсутствующей записи:

1. storage создаёт transient DTO без INSERT;
2. selector определяется по policy;
3. target version определяется для выбранного selector;
4. возвращается record с `id=null`.

### `schema()`

Manager получает `RequisitesSchema` для selector и передаёт её через `SchemaFieldResolver`. В результате клиент получает только актуальные поля и актуальные признаки required.

```php
$formPayload = $manager->schema($record, $context)->form->toArray();
```

### `validate()`

Метод возвращает обычный `PhpSoftBox\Validator\ValidationResult` и ничего не записывает:

```php
$validation = $manager->validate($record, $payload, $context);

if ($validation->hasErrors()) {
    $errors = $validation->errors();
}

$filtered = $validation->filteredData();
```

### `validateAndSave()`

Это основной write API:

```php
$result = $manager->validateAndSave($record, $payload, $context);

if (!$result->saved) {
    return $result->validation->errors();
}

$record = $result->record;
```

Гарантии метода:

- при validation errors storage не вызывается;
- сохраняется только `filteredData()`;
- selector и target version устанавливаются manager-ом;
- возвращается актуальная запись с ID, если storage предоставляет её;
- concurrent create не переключает selector существующей строки.

### `save()`

`save()` также валидирует payload, но при ошибках выбрасывает `RequisitesValidationFailedException`. Это удобно для внутренних данных, которые по контракту уже должны быть корректными, но обычно неудобно для HTTP form flow.

### `migrate()`

`migrate()` доводит record до target version текущего selector. Для persisted record изменённая версия сохраняется. Обычно вызывать его вручную не требуется: migration-aware storage выполняет lazy migration при чтении и записи.

## 6. Динамические поля формы

`FormFieldDefinition` поддерживает `visibleWhen` и `requiredWhen`.

Map-форма требует точного совпадения всех значений:

```php
new FormFieldDefinition(
    key: 'kpp',
    label: 'КПП',
    visibleWhen: ['organization_type' => 'legal'],
    requiredWhen: ['organization_type' => 'legal'],
);
```

List-форма позволяет задавать operators:

```php
new FormFieldDefinition(
    key: 'branch_code',
    label: 'Код филиала',
    visibleWhen: [
        [
            'field'    => 'organization_type',
            'operator' => 'in',
            'value'    => ['branch', 'representative_office'],
        ],
    ],
);
```

Поддерживаются `=`, `!=`, `in`, `not_in`. Условия вычисляются по текущему `record.payload`. Они влияют на описание формы, но не заменяют validation rules.

## 7. Standard storage

`ProfileStorageDefinition::default()` использует стандартные колонки:

```php
return ProfileStorageDefinition::default(
    table: 'requisites_records',
    connection: 'default',
    migrationAware: true,
);
```

`migrationAware=true` оборачивает adapter в `MigrationAwareStorageAdapter`.

`DefaultStorageAdapter::save()` является low-level upsert:

- persisted record обновляется по ID;
- transient record пытается выполнить INSERT;
- только unique violation может перейти в conflict recovery;
- FK, NOT NULL, invalid JSON и другие SQL errors не маскируются;
- INSERT выполняется в transaction/savepoint;
- recovery читает write connection, а не replica;
- нулевой affected-row count дополнительно проверяется, потому что некоторые DB возвращают `0` для unchanged values.

## 8. Кастомное ORM-хранилище

Profile может храниться в проектной ORM entity. Это полезно, когда таблице нужны собственные FK, indexes или дополнительные служебные поля.

Пример entity:

```php
use PhpSoftBox\Orm\Metadata\Attributes\Column;
use PhpSoftBox\Orm\Metadata\Attributes\Entity;
use PhpSoftBox\Orm\Metadata\Attributes\Id;

#[Entity(table: 'contract_requisites')]
final class ContractRequisitesEntity
{
    #[Id]
    #[Column(type: 'string')]
    public ?string $uuid = null;

    #[Column(name: 'owner_type', type: 'string')]
    public string $ownerType;

    #[Column(name: 'domain_owner_id', type: 'string')]
    public string $domainOwnerId;

    #[Column(name: 'profile_name', type: 'string')]
    public string $profileName;

    #[Column(name: 'schema_key', type: 'string')]
    public string $schemaKey;

    #[Column(name: 'payload_version', type: 'integer')]
    public int $payloadVersion;

    #[Column(name: 'data_json', type: 'json', nullable: true)]
    public array $data = [];

    #[Column(name: 'files_json', type: 'json', nullable: true)]
    public array $files = [];

    #[Column(name: 'created_at', type: 'string', nullable: true)]
    public ?string $createdAt = null;

    #[Column(name: 'updated_at', type: 'string', nullable: true)]
    public ?string $updatedAt = null;
}
```

Storage definition связывает стандартные роли Requisites с properties entity:

```php
return ProfileStorageDefinition::orm(
    entityClass: ContractRequisitesEntity::class,
    connection: 'contracts',
    migrationAware: true,
    ormFieldMap: [
        'idProperty'            => 'uuid',
        'profileProperty'       => 'profileName',
        'selectorProperty'      => 'schemaKey',
        'schemaVersionProperty' => 'payloadVersion',
        'subjectTypeProperty'   => 'ownerType',
        'subjectIdProperty'     => 'domainOwnerId',
        'payloadProperty'       => 'data',
        'attachmentsProperty'   => 'files',
        'createdAtProperty'     => 'createdAt',
        'updatedAtProperty'     => 'updatedAt',
    ],
);
```

Допустимые map keys:

- `idProperty`;
- `profileProperty`;
- `selectorProperty`;
- `schemaVersionProperty`;
- `subjectTypeProperty`;
- `subjectIdProperty`;
- `payloadProperty`;
- `attachmentsProperty`;
- `createdAtProperty`;
- `updatedAtProperty`.

Только `createdAtProperty` и `updatedAtProperty` могут быть `null`. Остальные значения должны быть непустыми строками и существовать в ORM metadata. Ошибки обнаруживаются при сборке factory.

Runtime storage и backfill используют один и тот же field map. Отдельно повторять имена колонок в CLI-конфигурации не нужно.

## 9. Безопасное первое сохранение

Между `load()` и `validateAndSave()` другой процесс может создать запись с тем же `subject + profile`.

Managed flow использует `AtomicCreateStorageInterface::insertOrFind()`:

```text
попытка INSERT
├── успех → вернуть вставленную canonical row
└── unique conflict → вернуть существующую row без UPDATE
                              ↓
                    manager проверяет selector
                    ├── совпал → разрешить UPDATE payload
                    └── различается → SelectorMismatchException
```

Проверка происходит до UPDATE, поэтому гонка не может переписать selector и начать трактовать существующий JSON по другой схеме.

Этот контракт нужен managed manager. Прямой low-level `save()` сохраняет upsert-семантику для совместимости.

## 10. Payload versions и migrators

Версия описывает формат JSON внутри одного `profile + selector`.

```php
public function targetVersions(): int|array
{
    return [
        'country:RU' => 3,
        'country:KZ' => 2,
        'default'    => 1,
    ];
}
```

Если версия одинакова для всех selectors, можно вернуть один `int`.

Миграции выполняются пошагово: `1 → 2`, затем `2 → 3`. Для каждого шага должен существовать ровно один подходящий migrator.

```php
use PhpSoftBox\Requisites\Contract\PayloadMigratorInterface;
use PhpSoftBox\Requisites\Migration\RequisitesMigrationContext;

final readonly class RenameTaxNumberMigrator implements PayloadMigratorInterface
{
    public function supports(
        string $profile,
        string $selector,
        int $fromVersion,
        int $toVersion,
    ): bool {
        return $profile === 'company'
            && $selector === 'country:RU'
            && $fromVersion === 1
            && $toVersion === 2;
    }

    public function migrate(array $payload, RequisitesMigrationContext $context): array
    {
        if (array_key_exists('inn', $payload)) {
            $payload['tax_number'] = $payload['inn'];
            unset($payload['inn']);
        }

        return $payload;
    }
}
```

Профиль возвращает migrators:

```php
public function migrators(): array
{
    return [
        new RenameTaxNumberMigrator(),
        new AddTaxRegistrationCountryMigrator(),
    ];
}
```

`RequisitesMigrationContext` содержит profile, selector, from/to version и record ID. Migrator не должен обращаться к HTTP request или глобальному tenant context: исторические записи должны одинаково мигрировать в web request и CLI.

## 11. Lazy migration

Если profile storage имеет `migrationAware=true`, factory добавляет `MigrationAwareStorageAdapter`.

При `find()`:

1. читается persisted record;
2. target version определяется по profile + persisted selector;
3. migration engine строит цепочку;
4. migrated record сохраняется;
5. вызывающий код получает новую версию.

При `save()` входящий persisted record также доводится до target version.

`create()` не вычисляет target version для временного selector `default`. В managed flow selector сначала выбирает manager, и только затем определяется target version. Это важно для профилей, у которых selectors имеют разные версии.

## 12. Backfill и CLI

Lazy migration обновляет только востребованные записи. Для массового обновления используется backfill:

```bash
php psb requisites:migrate --profile=company --dry-run
```

Опции:

| Опция | Значение |
|---|---|
| `--profile` | Обязательный профиль и его storage |
| `--selector` | Обрабатывать только один selector |
| `--from` | Пропускать записи с версией ниже указанной |
| `--to` | Явная target version вместо profile resolver |
| `--batch-size` | Размер batch, по умолчанию `100` |
| `--dry-run` | Выполнить migrations в памяти без UPDATE |

Примеры:

```bash
php psb requisites:migrate --profile=company --dry-run
php psb requisites:migrate --profile=company --selector=country:RU --from=1 --to=3 --batch-size=200
php psb requisites:migrate --profile=contract --dry-run
```

Runner получает storage через profile router. Поэтому `--profile=contract` автоматически выбирает connection, ORM table и полный field map профиля `contract`.

Backfill поддерживает `int|string` IDs. Batch pagination использует упорядочивание ID и условие `id > lastId`; строковая колонка ID должна иметь стабильный порядок сравнения в используемой БД.

Запись выполняется optimistic update по `id + previous schema version`. Если строка изменилась между чтением и UPDATE, ошибка попадает в report и чужие изменения не перезаписываются.

`BackfillMigrationReport` содержит:

- `processed`;
- `migrated`;
- `skipped`;
- `failed`;
- `errors`;
- `profile`;
- `storageDriver`;
- `connection`;
- `table`.

Неизвестный profile завершается ошибкой до SQL. `--dry-run` читает inner storage напрямую и не запускает lazy writes.

### Регистрация runner в DI

```php
$factory = new ManagedRequisitesFactory($database->manager());

$container->set(
    BackfillMigrationRunner::class,
    $factory->createBackfillRunner($profiles),
);
```

Пакет регистрирует CLI command definition, но application container должен предоставить `BackfillMigrationRunner` для `RequisitesMigrateHandler`.

## 13. Attachments policy

`AttachmentMapNormalizer` можно ограничить прикладной policy:

```php
use PhpSoftBox\Requisites\Contract\AttachmentKeyPolicyInterface;

final readonly class CompanyAttachmentPolicy implements AttachmentKeyPolicyInterface
{
    public function isAllowed(string $profile, string $selector, string $key): bool
    {
        return $profile === 'company'
            && in_array($key, ['seal', 'signature'], true);
    }
}
```

Нормализация выполняется отдельно от manager payload flow. Приложение решает, когда объединить нормализованную карту с текущими attachments record.

## 14. Typed exceptions

Основные исключения:

| Исключение | Когда возникает |
|---|---|
| `DuplicateProfileException` | Повторное имя в registry |
| `ProfileNotFoundException` | Неизвестный profile |
| `InvalidProfileStorageDefinitionException` | Некорректный driver, connection, table или entity class |
| `InvalidFieldMapException` | Неизвестный map key или отсутствующая ORM property |
| `MissingProfileValidatorException` | Для schema нет validator/fallback |
| `SchemaNotFoundException` | Schema provider не нашёл profile/selector и fallback |
| `SelectorMismatchException` | Existing record получил другой authoritative selector |
| `RequisitesValidationFailedException` | `save()` вызван с невалидным payload |
| `StorageException` | Нарушен storage contract или optimistic update |

Configuration exceptions следует обнаруживать при boot приложения. `SelectorMismatchException` и storage conflicts относятся к runtime.

## 15. Что тестировать в приложении

Сам компонент тестирует adapters на SQLite, MariaDB и PostgreSQL. В приложении всё равно нужны интеграционные тесты конкретных профилей:

- каждая schema имеет form validator;
- operation context выбирает ожидаемый selector;
- request payload не может переопределить server-owned selector;
- form schema соответствует validation rules;
- неизвестные поля не попадают в filtered payload;
- custom ORM field map соответствует реальной entity;
- каждый migration step преобразует production-подобный payload;
- CLI `--dry-run` выбирает правильную таблицу;
- existing record с другим selector не изменяется;
- consumer корректно показывает validation errors.

## 16. Когда использовать low-level API

Managed API подходит большинству проектов. Прямые adapters оправданы, если:

- lifecycle уже координирует другая библиотека;
- storage используется как часть offline ETL;
- payload заведомо сформирован доверенным кодом;
- нужен специализированный manager с другим публичным контрактом.

При этом low-level `StorageAdapterInterface::save()` не выполняет application validation. `FormValidationRequisitesValidator` остаётся permissive по умолчанию для обратной совместимости; strict behavior включается managed factory.

Для перевода уже существующего проекта перейдите к [главе 03](03-upgrade-notes.md).
