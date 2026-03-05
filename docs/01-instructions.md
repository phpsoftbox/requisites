# Requisites

Компонент `Requisites` хранит и валидирует произвольные наборы реквизитов в `payload` (JSON), без привязки к конкретной доменной сущности.

Типовые сценарии:
- реквизиты компании;
- паспортные данные пользователя;
- данные для заявлений/договоров.

См. также:
- `03-upgrade-notes.md`

## Ключевые возможности

- профильная модель (`profile`) для разных доменных форм;
- выбор схемы через `selector` (например `country:RU`, `template:v2`);
- хранение в общей таблице или в кастомной ORM-сущности;
- pipeline валидации (включая checksum-правила);
- обработка attachments отдельно от бизнес-полей;
- payload-миграции по версиям (`schema_version`), lazy и eager (CLI backfill).

## Базовые DTO

### `RequisitesSubject`
Идентификатор владельца реквизитов.

```php
new RequisitesSubject(type: 'company', id: 101);
```

### `RequisitesRecord`
Запись реквизитов.

Поля:
- `profile` — доменный профиль;
- `selector` — ключ выбора схемы;
- `schemaVersion` — версия payload;
- `subjectType`, `subjectId` — владелец;
- `payload` — данные реквизитов;
- `attachments` — карта файлов;
- `id` — ID записи в storage (если есть).

### `RequisitesSchema`
Декларативное описание формы:
- `profile`, `selector`, `version`;
- `form` — `PhpSoftBox\Forms\DTO\FormDefinition`;
- `meta` — произвольные метаданные.

## Контракты

Основные контракты компонента:
- `StorageAdapterInterface`
- `SchemaProviderInterface`
- `SelectorResolverInterface`
- `RequisitesValidatorInterface`
- `PayloadMigratorInterface`
- `PayloadMigrationRegistryInterface`
- `AttachmentKeyPolicyInterface`

`RequisitesManagerInterface` оставлен как orchestration-контракт. Конкретная manager-реализация собирается на уровне приложения.

## Хранение данных

### `DefaultStorageAdapter`
Работает с таблицей `requisites_records`:
- поиск по `(subject_type, subject_id, profile)`;
- `create/find/save`;
- upsert при конфликте уникальности;
- JSON-сериализация `payload_json` и `attachments_json`.

Рекомендуемая схема таблицы:

```php
$this->schema()->create('requisites_records', function (TableBlueprint $table): void {
    $table->id();
    $table->string('subject_type', 120);
    $table->string('subject_id', 64);
    $table->string('profile', 80);
    $table->string('selector', 120)->default('default');
    $table->json('payload_json')->nullable();
    $table->json('attachments_json')->nullable();
    $table->integer('schema_version')->default(1);
    $table->datetime('created_datetime')->useCurrent();
    $table->datetime('updated_datetime')->useCurrent()->useCurrentOnUpdate();

    $table->unique(['subject_type', 'subject_id', 'profile'], 'requisites_subject_profile_unique');
    $table->index(['profile', 'selector'], 'requisites_profile_selector_index');
});
```

### `OrmEntityStorageAdapter`
Адаптер для работы с проектной ORM-сущностью.

Если названия полей в entity отличаются от стандартных, настройте `OrmEntityFieldMap`:

```php
new OrmEntityStorageAdapter(
    connections: $database->manager(),
    entityClass: CompanyRequisitesEntity::class,
    fieldMap: new OrmEntityFieldMap(
        selectorProperty: 'countryCode',
        schemaVersionProperty: 'schemaVersion',
    ),
);
```

## Схемы и selector

### `ArraySchemaProvider`
Хранит схемы в массиве `profile => selector => RequisitesSchema` и поддерживает fallback на `default`.

### `FallbackSelectorResolver`
Резолвит selector в порядке:
1. `payload[$selectorKey]`
2. `context[$selectorKey]`
3. `defaultSelector`

По умолчанию `selectorKey = "selector"`, `defaultSelector = "default"`.

### `SchemaFieldResolver`
Применяет динамические условия в схеме:
- `visibleWhen` — отображение поля;
- `requiredWhen` — динамическая обязательность.

Поддерживаются форматы условий:
- map-формат: `['org_type' => 'legal']`;
- list-формат c `field/operator/value` (`=`, `!=`, `in`, `not_in`).

## Валидация

Пакет оставляет валидацию pluggable через `RequisitesValidatorInterface`.
Рекомендуемый путь: валидировать payload в form-слое приложения (`FormValidationInterface`/`AbstractFormValidation` или `RequestSchema`) и в `Requisites` использовать единый `PhpSoftBox\Validator\ValidationResult`.

### Готовые правила для `phpsoftbox/validator`

Встроенные checksum-правила:
- `InnChecksumValidation`
- `OgrnByTypeValidation`
- `BankAccountChecksumValidation`

Пример:

```php
$result = $validator->validate('company', 'country:RU', $payload);

if ($result->hasErrors()) {
    $errors = $result->errors();
}

$normalizedPayload = $result->filteredData();
```

## Attachments

### `AttachmentMapNormalizer`
Нормализует и мерджит карту attachments:
- фильтрация ключей через `AttachmentKeyPolicyInterface`;
- удаление ключа через `null` или `''` в patch;
- нормализация scalar/`Stringable`/`['path' => ...]`.

Политика по умолчанию: `AllowAllAttachmentKeyPolicy`.

## Payload-миграции

### Модель версий
- `schemaVersion` хранится в записи;
- миграции идут строго пошагово: `N -> N+1`;
- целевая версия определяется через `MigrationTargetVersionResolverInterface`.

### Компоненты
- `PayloadMigrationRegistry` — строит корректную цепочку миграторов;
- `PayloadMigrationEngine` — выполняет цепочку миграции payload;
- `MigrationAwareStorageAdapter` — lazy-миграция на `find/save`;
- `BackfillMigrationRunner` — eager/backfill для таблицы;
- `BackfillMigrationReport` — отчет (`processed/migrated/skipped/failed/errors`);
- `StaticTargetVersionResolver` — простая конфигурация target version.

### Lazy migration

```php
$storage = new MigrationAwareStorageAdapter(
    inner: new DefaultStorageAdapter($connections),
    migrationEngine: $engine,
    targetResolver: $targetResolver,
);
```

Поведение:
- на `find()` устаревшая запись мигрируется и сохраняется;
- на `save()` входящая запись доводится до target version перед записью.

### Eager migration (backfill)

```php
$report = $runner->run(
    profile: 'company',
    selector: 'country:RU',
    fromVersion: 1,
    toVersion: 3,
    dryRun: true,
    batchSize: 200,
);
```

## CLI

Пакет регистрирует команду:
- `requisites:migrate`

Опции:
- `--profile` (required)
- `--selector`
- `--from`
- `--to`
- `--batch-size` (default `100`)
- `--dry-run`

Примеры:

```bash
php psb requisites:migrate --profile=company --dry-run
php psb requisites:migrate --profile=company --selector=country:RU --from=1 --to=3 --batch-size=200
```

## Минимальный сценарий интеграции

1. Создать таблицу `requisites_records` (или свою entity-таблицу).
2. Подключить storage (`DefaultStorageAdapter` или `OrmEntityStorageAdapter`).
3. Настроить схемы (`ArraySchemaProvider`) и selector (`FallbackSelectorResolver`).
4. Подключить реализацию `RequisitesValidatorInterface` (обычно через `RequestSchema` приложения).
5. При необходимости подключить `MigrationAwareStorageAdapter`.
6. Для миграции исторических данных запускать `requisites:migrate`.

## Проверка пакета

```bash
cd /home/hej/dev/www/phpsoftbox
make select-requisites
make php-test
```
