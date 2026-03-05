# 01. Первое подключение Requisites

Эта глава рассчитана на читателя, который раньше не работал с компонентом. В результате получится один профиль реквизитов, хранящийся в стандартной таблице, с динамической схемой формы, валидацией и безопасным сохранением.

Дальнейшее чтение:

- [02 — Managed lifecycle](02-managed-lifecycle.md): несколько профилей, selector policies, ORM storage и payload-миграции;
- [03 — Обновление существующего проекта](03-upgrade-notes.md): переход с прежнего или проектного API.

## 1. Когда нужен этот компонент

`phpsoftbox/requisites` предназначен для данных, состав которых зависит от типа владельца, страны, шаблона или другого признака и может меняться со временем.

Например, набор банковских реквизитов российской компании отличается от набора реквизитов компании из Казахстана. Создавать отдельную колонку доменной таблицы для каждого возможного поля неудобно. Requisites хранит такой набор в JSON payload, но добавляет поверх JSON:

- выбор схемы;
- описание формы для клиента;
- валидацию и фильтрацию перед записью;
- версию структуры;
- миграции старых payload;
- стандартное или проектное хранилище.

Компонент не заменяет обычные колонки для стабильных доменных данных. Если поле всегда существует, участвует в индексах и часто используется в SQL-фильтрах, его обычно лучше оставить отдельной колонкой.

Минимальная версия PHP — 8.5.

## 2. Основные понятия

Одна запись описывается четырьмя ключевыми понятиями:

| Понятие | Значение | Пример |
|---|---|---|
| Subject | Владелец данных | компания с ID `42` |
| Profile | Назначение набора данных | `company` или `contract` |
| Selector | Вариант схемы внутри профиля | `country:RU` |
| Schema version | Версия структуры payload | `2` |

Subject передаётся как `RequisitesSubject`:

```php
use PhpSoftBox\Requisites\DTO\RequisitesSubject;

$subject = new RequisitesSubject(
    type: 'company',
    id: 42,
);
```

ID может быть `int` или `string`, поэтому UUID и другие строковые идентификаторы поддерживаются без отдельного адаптера.

Для одной пары `subject + profile` существует не более одной записи. Selector является частью содержимого записи, а не частью её уникального ключа.

## 3. Как выглядит обычный запрос

Managed API выполняет следующий flow:

```text
subject + profile + operation context
                ↓
        load существующей записи
        или создание transient record
                ↓
      выбор selector и schema version
                ↓
      schema() для отображения формы
                ↓
 validateAndSave(request payload)
                ↓
 ValidationResult + сохранённый record
```

`operation context` — серверные данные текущей операции. Например, страна компании, тариф или тип документа. Контекст передаётся в каждый вызов явно; manager не хранит его в глобальном или mutable состоянии.

## 4. Создание таблицы

Для первого профиля проще использовать `DefaultStorageAdapter` и стандартный набор колонок:

```php
use PhpSoftBox\Database\SchemaBuilder\TableBlueprint;

$this->schema()->create('requisites_records', static function (TableBlueprint $table): void {
    $table->id();
    $table->string('subject_type', 120);
    $table->string('subject_id', 64);
    $table->string('profile', 80);
    $table->string('selector', 120)->default('default');
    $table->integer('schema_version')->default(1);
    $table->json('payload_json')->nullable();
    $table->json('attachments_json')->nullable();
    $table->datetime('created_datetime');
    $table->datetime('updated_datetime');

    $table->unique(
        ['subject_type', 'subject_id', 'profile'],
        'requisites_subject_profile_unique',
    );
    $table->index(
        ['profile', 'selector'],
        'requisites_profile_selector_index',
    );
});
```

Имена колонок важны только для standard storage. Кастомная ORM-таблица и mapping описаны в [главе 02](02-managed-lifecycle.md#8-кастомное-orm-хранилище).

## 5. Описание формы

`RequisitesSchema` сообщает клиенту, какие поля показать. Это описание интерфейса, а не класс валидации.

```php
use PhpSoftBox\Forms\DTO\FormDefinition;
use PhpSoftBox\Forms\DTO\FormFieldDefinition;
use PhpSoftBox\Requisites\DTO\RequisitesSchema;

$schema = new RequisitesSchema(
    profile: 'company',
    selector: 'default',
    version: 1,
    form: new FormDefinition(
        id: 'company-requisites',
        title: 'Реквизиты компании',
        fields: [
            new FormFieldDefinition(
                key: 'organization_name',
                label: 'Название организации',
                required: true,
            ),
            new FormFieldDefinition(
                key: 'tax_number',
                label: 'Налоговый номер',
                required: true,
            ),
        ],
    ),
);
```

`SchemaFieldResolver` также умеет скрывать поля и менять их обязательность через `visibleWhen` и `requiredWhen`. Это рассматривается в главе 02.

## 6. Валидация и фильтрация payload

Form validation отвечает сразу за две вещи:

1. нормализует входные значения фильтрами;
2. проверяет нормализованный payload правилами.

```php
use PhpSoftBox\Filter\TrimFilter;
use PhpSoftBox\Requisites\Form\AbstractRequisitesFormValidation;
use PhpSoftBox\Validator\Rule\StringValidation;

final class CompanyRequisitesFormValidation extends AbstractRequisitesFormValidation
{
    public function beforeValidation(): void
    {
        $this->applyFilters([
            'organization_name' => [new TrimFilter()],
            'tax_number'        => [new TrimFilter()],
        ]);
    }

    public function rules(): array
    {
        return [
            'organization_name' => [
                new StringValidation()->required()->min(1)->max(255),
            ],
            'tax_number' => [
                new StringValidation()->required()->min(3)->max(32),
            ],
        ];
    }
}
```

Manager сохраняет только `ValidationResult::filteredData()`. Поля, которые form validation не пропустила в результат, в storage не попадут.

## 7. Создание профиля

Профиль связывает имя, схемы, validators, storage и версии payload.

```php
use PhpSoftBox\Requisites\Contract\RequisitesProfileInterface;
use PhpSoftBox\Requisites\Profile\ProfileStorageDefinition;

final readonly class CompanyRequisitesProfile implements RequisitesProfileInterface
{
    public function __construct(
        private RequisitesSchema $schema,
    ) {
    }

    public function profile(): string
    {
        return 'company';
    }

    public function selectorKey(): string
    {
        return 'selector';
    }

    public function defaultSelector(): string
    {
        return 'default';
    }

    public function schemas(): array
    {
        return ['default' => $this->schema];
    }

    public function targetVersions(): int|array
    {
        return 1;
    }

    public function formValidationClasses(): array
    {
        return ['default' => CompanyRequisitesFormValidation::class];
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
        return [];
    }
}
```

Для каждой схемы должен существовать validator с тем же selector либо validator с ключом `default`. Ошибка обнаруживается при создании registry, до первого пользовательского запроса.

## 8. Сборка manager

Registry принимает готовые экземпляры профилей. Он не создаёт классы через `new` и не зависит от конкретного DI container.

```php
use PhpSoftBox\Requisites\Profile\ArrayRequisitesProfileRegistry;
use PhpSoftBox\Requisites\Profile\ManagedRequisitesFactory;

$profiles = new ArrayRequisitesProfileRegistry([
    new CompanyRequisitesProfile($schema),
]);

$factory = new ManagedRequisitesFactory($database->manager());
$manager = $factory->createManager($profiles);
```

Обычно `$profiles`, `$factory` и `$manager` регистрируются как singleton services приложения. Сам manager не содержит request-specific mutable state, поэтому один экземпляр безопасно использовать для последовательных операций.

## 9. Загрузка схемы и сохранение

```php
$subject = new RequisitesSubject('company', 42);

$record = $manager->load(
    subject: $subject,
    profile: 'company',
);

$form = $manager->schema($record)->form->toArray();

$result = $manager->validateAndSave(
    record: $record,
    payload: [
        'organization_name' => '  Example LLC  ',
        'tax_number'        => ' 123456789 ',
        'unexpected'        => 'this field must not be stored',
    ],
);

if (!$result->saved) {
    return [
        'form'   => $form,
        'errors' => $result->validation->errors(),
    ];
}

$savedRecord = $result->record;
```

После успешной обработки `organization_name` и `tax_number` будут сохранены без окружающих пробелов. `unexpected` отсутствует в filtered payload и не попадёт в JSON.

`validateAndSave()` — предпочтительный API для HTTP/form flow: ошибки валидации возвращаются как данные и не требуют исключения.

Метод `save()` является convenience-вариантом. Он также выполняет валидацию, но при validation errors выбрасывает `RequisitesValidationFailedException`. Не используйте его в месте, где некорректный пользовательский ввод является ожидаемым сценарием.

## 10. Что хранится в record

`RequisitesRecord` — immutable DTO со следующими полями:

- `profile` — имя профиля;
- `selector` — выбранная схема;
- `schemaVersion` — версия payload;
- `subjectType`, `subjectId` — владелец;
- `payload` — отфильтрованные бизнес-данные;
- `attachments` — отдельная карта файлов;
- `id` — ID строки storage или `null` для transient record.

`load()` не обязан немедленно вставлять отсутствующую запись. До первого успешного сохранения record остаётся transient и имеет `id=null`.

## 11. Attachments

Attachments хранятся отдельно от payload, чтобы бизнес-поля и ссылки на файлы можно было обрабатывать разными политиками.

`AttachmentMapNormalizer`:

- пропускает ключи через `AttachmentKeyPolicyInterface`;
- принимает строку, scalar, `Stringable` или `['path' => ...]`;
- удаляет значение при `null` или пустой строке в patch;
- по умолчанию использует `AllowAllAttachmentKeyPolicy`.

Manager не изменяет attachments автоматически при сохранении payload: новый `RequisitesRecord` должен содержать нужную карту attachments.

## 12. Managed API и low-level API

Для нового приложения рекомендуется managed API:

- `ArrayRequisitesProfileRegistry`;
- `ManagedRequisitesFactory`;
- `DefaultRequisitesManager`;
- `validateAndSave()`.

Low-level API нужен для специализированной инфраструктуры и совместимости:

- `DefaultStorageAdapter`;
- `OrmEntityStorageAdapter`;
- `FormValidationRequisitesValidator`;
- `PayloadMigrationEngine`;
- `ProfileRouterStorageAdapter`.

При прямом использовании low-level storage компонент не может гарантировать, что payload уже прошёл application validation. Эта ответственность остаётся на вызывающем коде.

## 13. Проверка компонента

Из корня монорепозитория:

```bash
make select-requisites
make php-test
make php-composer-cs-check
```

Следующий шаг — [02 — Managed lifecycle](02-managed-lifecycle.md), где один профиль расширяется до реального multi-profile сценария.
