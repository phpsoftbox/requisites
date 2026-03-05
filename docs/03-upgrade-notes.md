# 03. Обновление существующего проекта

Эта глава предназначена для проекта, который уже хранит реквизиты через `phpsoftbox/requisites` либо имеет собственные profile-, storage- и validation-адаптеры поверх компонента.

Если Requisites подключается впервые, начните с [главы 01](01-instructions.md). Термины managed lifecycle, selector policy и payload migration подробно разобраны в [главе 02](02-managed-lifecycle.md).

Навигация:

- [01 — Первое подключение](01-instructions.md);
- [02 — Managed lifecycle](02-managed-lifecycle.md).

## 1. Цель обновления

После перехода приложение должно описывать каждый вид реквизитов одним профилем, а типовой порядок загрузки, выбора схемы, валидации, миграции и сохранения должен выполнять компонент:

```text
До обновления
action/service
├── сам выбирает storage
├── сам определяет selector
├── сам ищет validator
├── сам вызывает payload migrations
└── может случайно сохранить исходный payload

После обновления
action/service
└── DefaultRequisitesManager::validateAndSave()
        ├── profile registry
        ├── selector policy
        ├── strict form validation
        ├── filtered payload
        ├── payload version
        └── profile-routed storage
```

Переход не требует одновременно переписывать всех потребителей. Low-level contracts и adapters сохранены, поэтому профили и action-ы можно переводить поэтапно.

Минимальная версия PHP компонента — 8.5.

## 2. Что изменилось в поведении

Перед изменением кода важно отличать новые классы от новых гарантий поведения.

| Область | Прежний или low-level flow | Managed flow |
|---|---|---|
| Конфигурация | Ошибка могла обнаружиться при первом запросе | Registry проверяет профили при сборке |
| Selector новой записи | Выбирался прикладным кодом | Выбирается по `SelectorResolutionPolicy` |
| Selector существующей записи | Мог быть перезаписан вместе с payload | Считается неизменяемым; mismatch приводит к исключению |
| Validator | При отсутствии формы payload мог пройти без ошибок | Для каждой schema обязателен validator или `default` fallback |
| Сохраняемые данные | Вызывающий код мог передать исходный payload | `validateAndSave()` сохраняет только `filteredData()` |
| Ошибки формы | Зависели от проектной обвязки | Возвращаются в `RequisitesSaveResult`, запись не выполняется |
| Первое сохранение | Обычный upsert | Atomic create защищает от гонки между `load()` и INSERT |
| Версия transient record | Могла вычисляться для временного `default` selector | Сначала выбирается selector, затем его target version |
| Backfill | Мог требовать отдельной конфигурации таблицы | Использует storage definition выбранного профиля |

## 3. Подготовка: составьте карту текущей интеграции

До удаления проектного кода найдите всех потребителей. Обычно нужно проверить:

- реализации profile contract;
- места создания `DefaultStorageAdapter`, `OrmEntityStorageAdapter` и `MigrationAwareStorageAdapter`;
- собственные storage routers и factories;
- классы валидации и фильтрации payload;
- ручные последовательности `validate()` и `save()`;
- вызовы `StorageAdapterInterface::save()` напрямую;
- payload migrators и способы вычисления target version;
- CLI-команды или cron-задачи для массовой миграции;
- таблицы, connections и уникальные индексы;
- внешние проекты, которые используют этот компонент как библиотеку.

Для каждого профиля зафиксируйте:

| Что записать | Пример |
|---|---|
| Имя profile | `company` |
| Selector key | `country` |
| Default selector | `country:RU` |
| Все selectors | `country:RU`, `country:KZ` |
| Target version каждого selector | `3`, `2` |
| Storage | connection, table или ORM entity |
| ID storage | `int` или `string` |
| Form validation | класс для каждого selector |
| Migrators | все шаги `N → N+1` |
| Владелец selector | пользователь или серверный контекст |

Эта карта нужна не только для переноса кода. По ней проверяются данные перед первым write backfill.

## 4. Шаг 1. Обновите платформенные требования

Убедитесь, что приложение и среда выполнения поддерживают PHP 8.5. Компонент также использует актуальные версии пакетов:

- `phpsoftbox/validator`;
- `phpsoftbox/forms`;
- `phpsoftbox/filter`;
- `phpsoftbox/database`;
- `phpsoftbox/orm` для ORM storage.

В частности, в `phpsoftbox/validator` должны быть доступны:

- `PhpSoftBox\Validator\AbstractFormValidation`;
- `PhpSoftBox\Validator\Support\FilterPayloadApplier`.

Обновляйте зависимости штатным для проекта способом — через его Makefile или PHP-контейнер. Не копируйте локальный `vendor` между проектами.

## 5. Шаг 2. Перенесите профиль на пакетный контракт

Если в проекте есть собственный аналог profile interface, замените его на `PhpSoftBox\Requisites\Contract\RequisitesProfileInterface`.

Профиль должен полностью описывать один вид данных:

```php
use PhpSoftBox\Requisites\Contract\RequisitesProfileInterface;
use PhpSoftBox\Requisites\DTO\RequisitesSchema;
use PhpSoftBox\Requisites\Profile\ProfileStorageDefinition;

final readonly class CompanyRequisitesProfile implements RequisitesProfileInterface
{
    public function __construct(
        private RequisitesSchema $ruSchema,
        private RequisitesSchema $kzSchema,
    ) {
    }

    public function profile(): string
    {
        return 'company';
    }

    public function selectorKey(): string
    {
        return 'country';
    }

    public function defaultSelector(): string
    {
        return 'country:RU';
    }

    public function schemas(): array
    {
        return [
            'country:RU' => $this->ruSchema,
            'country:KZ' => $this->kzSchema,
        ];
    }

    public function targetVersions(): int|array
    {
        return [
            'country:RU' => 3,
            'country:KZ' => 2,
        ];
    }

    public function formValidationClasses(): array
    {
        return [
            'country:RU' => CompanyRequisitesRuFormValidation::class,
            'country:KZ' => CompanyRequisitesKzFormValidation::class,
        ];
    }

    public function storageDefinition(): ProfileStorageDefinition
    {
        return ProfileStorageDefinition::default(
            table: 'requisites_records',
            connection: 'default',
            migrationAware: true,
        );
    }

    public function migrators(): array
    {
        return [
            new CompanyRuV1ToV2Migrator(),
            new CompanyRuV2ToV3Migrator(),
            new CompanyKzV1ToV2Migrator(),
        ];
    }
}
```

Registry проверит, что:

- имя profile не пустое и не повторяется;
- для default selector существует точная schema либо schema с ключом `default`;
- `profile` и `selector` внутри каждой `RequisitesSchema` совпадают с ключами профиля;
- для каждой schema определена target version;
- для каждой schema найден form validation class;

Storage definition дополнительно проверяется при создании adapters через `ManagedRequisitesFactory`: для ORM storage должен существовать класс entity, а field map должен содержать только поддерживаемые keys.

Не оставляйте временные пустые schemas или validators: managed factory намеренно считает неполный профиль ошибкой конфигурации.

## 6. Шаг 3. Перенесите validation и filters

Form validation должен наследоваться от `AbstractRequisitesFormValidation`:

```php
use PhpSoftBox\Filter\TrimFilter;
use PhpSoftBox\Requisites\Form\AbstractRequisitesFormValidation;
use PhpSoftBox\Validator\Rule\StringValidation;

final class CompanyRequisitesRuFormValidation extends AbstractRequisitesFormValidation
{
    public function beforeValidation(): void
    {
        $this->applyFilters([
            'organization_name' => [new TrimFilter()],
            'organization_inn'  => [new TrimFilter()],
        ]);
    }

    public function rules(): array
    {
        return [
            'organization_name' => [
                new StringValidation()->required()->max(255),
            ],
            'organization_inn' => [
                new StringValidation()->required(),
            ],
        ];
    }
}
```

Filters должны выполняться до rules. Не нормализуйте те же значения повторно в action или repository: иначе отображаемая форма, проверяемые данные и сохранённый JSON могут расходиться.

Результат `RequisitesValidatorInterface::validate()` теперь использует общий `PhpSoftBox\Validator\ValidationResult`. Legacy-класс `PhpSoftBox\Requisites\Validation\ValidationResult` больше не должен фигурировать в type hints потребителя.

### Strict и permissive режимы

При прямом создании `FormValidationRequisitesValidator` параметр `strict` по умолчанию равен `false`. Это оставлено для совместимости low-level интеграций: отсутствующий form class пропускает payload без ошибок.

`ManagedRequisitesFactory` всегда собирает strict validator. Если schema не имеет собственного form class и не находит `default` fallback, сборка завершается `MissingProfileValidatorException`.

Для нового и переводимого кода следует полагаться на strict managed behavior, а не создавать permissive validator вручную.

## 7. Шаг 4. Опишите storage каждого профиля

Для общей таблицы:

```php
return ProfileStorageDefinition::default(
    table: 'requisites_records',
    connection: 'default',
    migrationAware: true,
);
```

Для проектной ORM entity:

```php
return ProfileStorageDefinition::orm(
    entityClass: CompanyRequisitesEntity::class,
    connection: 'tenant',
    migrationAware: true,
    ormFieldMap: [
        'idProperty'            => 'uuid',
        'profileProperty'       => 'profileName',
        'selectorProperty'      => 'schemaKey',
        'schemaVersionProperty' => 'payloadVersion',
        'subjectTypeProperty'   => 'ownerType',
        'subjectIdProperty'     => 'ownerId',
        'payloadProperty'       => 'data',
        'attachmentsProperty'   => 'files',
        'createdAtProperty'     => 'createdAt',
        'updatedAtProperty'     => 'updatedAt',
    ],
);
```

Проверьте уникальный индекс по `subject + profile`. Именно он обеспечивает контракт «не более одной записи на владельца и профиль» и позволяет безопасно разрешать concurrent INSERT.

ORM field map является единым источником истины и для runtime, и для backfill. Не создавайте отдельную CLI-карту тех же properties.

## 8. Шаг 5. Соберите registry, manager и CLI runner

```php
use PhpSoftBox\Requisites\Contract\ManagedRequisitesManagerInterface;
use PhpSoftBox\Requisites\Migration\BackfillMigrationRunner;
use PhpSoftBox\Requisites\Profile\ArrayRequisitesProfileRegistry;
use PhpSoftBox\Requisites\Profile\ManagedRequisitesFactory;
use PhpSoftBox\Requisites\Schema\SelectorResolutionPolicy;

$profiles = new ArrayRequisitesProfileRegistry([
    $companyProfile,
    $contractProfile,
]);

$factory = new ManagedRequisitesFactory($database->manager());

$manager = $factory->createManager($profiles, [
    'company' => SelectorResolutionPolicy::CONTEXT_ONLY,
]);

$backfill = $factory->createBackfillRunner($profiles);

$container->set(ManagedRequisitesManagerInterface::class, $manager);
$container->set(BackfillMigrationRunner::class, $backfill);
```

Пакет предоставляет определение команды `requisites:migrate`, но application container должен уметь выдать `BackfillMigrationRunner` её handler-у.

### Как выбрать selector policy

- `CONTEXT_ONLY` — selector принадлежит серверной модели: страна компании, tenant, тип договора;
- `PAYLOAD_ONLY` — selector является частью пользовательского ввода;
- `CONTEXT_FIRST` — серверный context предпочтителен, payload разрешён как fallback;
- `PAYLOAD_FIRST` — сохраняет прежнее поведение: payload, затем context, затем default.

Если пользователь не должен иметь возможность выбрать другую схему, используйте `CONTEXT_ONLY`. Не полагайтесь на то, что UI не отправит поле selector.

## 9. Шаг 6. Переведите write flow на `validateAndSave()`

Опасный ручной вариант выглядит так:

```php
$validation = $validator->validate($profile, $selector, $payload);

if (!$validation->hasErrors()) {
    // Здесь легко ошибочно сохранить $payload вместо filteredData().
    $storage->save($recordWithRawPayload);
}
```

Managed-вариант:

```php
use PhpSoftBox\Requisites\DTO\RequisitesSubject;

$context = [
    'country' => 'country:' . $company->countryCode,
];

$record = $manager->load(
    subject: new RequisitesSubject('company', $company->id),
    profile: 'company',
    context: $context,
);

$result = $manager->validateAndSave(
    record: $record,
    payload: $requestPayload,
    context: $context,
);

if (!$result->saved) {
    return ['errors' => $result->validation->errors()];
}

$savedRecord = $result->record;
```

Передавайте один и тот же operation context в `load()`, `schema()` и `validateAndSave()`. Manager не сохраняет его между вызовами.

`save()` также валидирует данные, но при validation errors выбрасывает `RequisitesValidationFailedException`. Для формы, где неверный пользовательский ввод ожидаем, используйте `validateAndSave()`.

## 10. Важное правило: selector существующей записи неизменяем

Для записи с ID сохранённый `record.selector` считается authoritative. Если payload или operation context содержит другое непустое значение selector key, manager выбрасывает `SelectorMismatchException` до UPDATE.

Это изменение может обнаружить старый код, который неявно переводил существующий JSON с одной схемы на другую. Такой переход нельзя заменять обычной payload migration: payload migrator изменяет версии только внутри одного selector.

Если бизнес-процесс требует смены selector, реализуйте отдельную явную операцию:

1. загрузите существующую запись;
2. проверьте разрешённость перехода;
3. преобразуйте либо очистите payload;
4. провалидируйте его по новой схеме;
5. сохраните изменение через специально предназначенный application service.

Обычный update action не должен выполнять такой переход случайно.

## 11. Изменение `MigrationAwareStorageAdapter::create()`

Раньше low-level adapter мог вычислять target version сразу при `create()`, когда настоящий selector ещё неизвестен и record временно имел selector `default`. Это некорректно для профиля, где разные selectors имеют разные target versions.

Теперь `create()` только возвращает transient record. В managed flow дальнейший порядок такой:

1. manager определяет selector по policy;
2. resolver получает target version именно этого selector;
3. manager возвращает готовый transient record.

Если проект использует `MigrationAwareStorageAdapter` напрямую, он должен сам установить корректные selector и schema version перед `save()`. Сам `save()` по-прежнему мигрирует persisted record к target version.

## 12. Payload migrations и backfill

До развертывания проверьте, что для каждой старой версии существует непрерывная цепочка `1 → 2 → 3`.

Migrator должен быть детерминированным и работать только с переданным payload и `RequisitesMigrationContext`. Не читайте из него HTTP request, текущий route или mutable tenant singleton: тот же код выполняется из CLI.

Сначала запустите dry run отдельно для каждого профиля:

```bash
php psb requisites:migrate --profile=company --dry-run
php psb requisites:migrate --profile=contract --dry-run
```

В отчёте проверьте profile, storage driver, connection, table, счётчики `processed`, `migrated`, `skipped`, `failed` и список `errors`.

После этого выполните write backfill:

```bash
php psb requisites:migrate \
    --profile=company \
    --selector=country:RU \
    --from=1 \
    --to=3 \
    --batch-size=200
```

Backfill поддерживает `int|string` ID. Для строкового ID база должна обеспечивать стабильный порядок сравнения, поскольку batch pagination использует `id > lastId`.

UPDATE защищён предыдущей schema version. Если строка изменилась после чтения batch, runner не перезапишет конкурентное изменение, а добавит ошибку в report.

## 13. Safe upsert и первое сохранение

Между `load()` отсутствующей записи и `validateAndSave()` другой процесс может вставить ту же пару `subject + profile`. Managed storage выполняет atomic `insertOrFind()`:

- INSERT успешен — используется новая строка;
- получен unique conflict — читается уже существующая строка;
- selector совпадает — payload можно обновить;
- selector отличается — выбрасывается `SelectorMismatchException` до UPDATE.

Только unique violation запускает conflict recovery. Ошибки FK, NOT NULL, invalid JSON и другие ошибки БД больше не маскируются под upsert conflict.

Low-level `StorageAdapterInterface::save()` сохраняет прежнюю upsert-семантику для обратной совместимости. Гарантия неизменяемого selector относится к managed manager.

## 14. Готовые формы и правила company profile

Компонент содержит каталог стран `PhpSoftBox\Requisites\Country\RequisitesCountryCatalog` и form validation classes в namespace `PhpSoftBox\Requisites\Validation\Form\Company`:

- `CompanyRequisitesRuFormValidation` для `country:RU`;
- `CompanyRequisitesKzFormValidation` для `country:KZ`;
- `CompanyRequisitesByFormValidation` для `country:BY`;
- `CompanyRequisitesAmFormValidation` для `country:AM`;
- `CompanyRequisitesAzFormValidation` для `country:AZ`;
- `CompanyRequisitesGenericFormValidation` как упрощённый fallback.

Страновые rules разнесены по namespace `Validation\Rule\Ru`, `Kz`, `By`, `Am` и `Az`.

Не подключайте готовую форму только из-за совпадения страны. Сначала сравните её поля и правила с контрактом конкретного приложения.

## 15. Рекомендуемый порядок развертывания

Безопаснее разделить обновление на этапы:

1. Обновить runtime до PHP 8.5 и совместимые версии пакетов.
2. Добавить package profiles, validators и storage definitions рядом со старым кодом.
3. Собрать registry при boot и устранить все configuration exceptions.
4. Добавить интеграционные тесты manager для каждого selector.
5. Зарегистрировать `BackfillMigrationRunner` и выполнить `--dry-run`.
6. Перевести read/schema flow одного профиля.
7. Перевести его write flow на `validateAndSave()`.
8. Проверить метрики и ошибки на тестовой среде.
9. Выполнить write backfill.
10. Повторить для остальных профилей.
11. Удалить проектные adapters только после поиска оставшихся потребителей.

Не удаляйте старую инфраструктуру в том же изменении, в котором впервые переключаются все action-ы: это усложняет сравнение поведения и rollback.

## 16. Что проверить тестами

Минимальный набор интеграционных сценариев для каждого профиля:

- отсутствующая запись загружается как transient record с правильными selector и target version;
- существующая запись читается из нужных connection и table;
- `schema()` возвращает форму выбранного selector;
- filters выполняются до validation;
- неизвестные поля не сохраняются;
- validation errors не вызывают запись в storage;
- `CONTEXT_ONLY` игнорирует selector из payload для новой записи;
- существующая запись отклоняет другой selector из context и payload;
- конкурентное первое сохранение не перезаписывает чужой selector;
- lazy migration и CLI backfill дают одинаковый payload;
- ORM field map работает и для runtime, и для CLI;
- `--dry-run` не выполняет UPDATE;
- строковый ID корректно проходит несколько batch.

После component tests отдельно проверьте реальные HTTP-ответы и формы приложения. Компонент гарантирует lifecycle данных, но не знает, как конкретный проект преобразует `ValidationResult` в API response.

## 17. Checklist перед удалением проектных адаптеров

- [ ] Все профили зарегистрированы в `ArrayRequisitesProfileRegistry`.
- [ ] Для каждой schema существует form validation class или осознанный `default` fallback.
- [ ] Для каждого selector задана target version.
- [ ] Каждый migration step покрыт тестом с production-подобным payload.
- [ ] Selector policy явно выбрана для server-owned selectors.
- [ ] Все write action-ы используют `validateAndSave()` либо осознанный low-level flow.
- [ ] Уникальный индекс `subject + profile` существует во всех storage.
- [ ] Custom ORM field map соответствует metadata entity.
- [ ] CLI runner зарегистрирован в application container.
- [ ] Dry run выполнен отдельно для каждого профиля.
- [ ] Найдены все внешние потребители удаляемых project interfaces.
- [ ] Изменения, необходимые внешним проектам, переданы их командам отдельно.

После выполнения checklist приложение использует пакет как единый orchestration layer, а проектный код отвечает только за предметные схемы, правила, storage entities и преобразования payload.
