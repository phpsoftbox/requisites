# Requisites (Design Draft)

Компонент для хранения и валидации реквизитов/паспортных данных/заявлений в унифицированном виде.

Статус: `draft` (дизайн и план внедрения).

## Что решает
- Не привязан к одной предметной области (`Company`).
- Поддерживает разные сущности-владельцы (компания, пользователь, заявление и т.д.).
- Поддерживает разные схемы полей и валидации, в том числе country-specific.
- Позволяет использовать как общую таблицу, так и кастомные таблицы проекта.

## Документация
- [01-instructions.md](docs/01-instructions.md)
  - включает детальную стратегию `payload`-миграций (`schema_version`, lazy/eager, backfill);
  - включает обязательную тестовую стратегию и DoD по этапам.

## Payload Migrations
- `PayloadMigrationEngine` — выполняет пошаговые `N -> N+1` миграции payload.
- `MigrationAwareStorageAdapter` — lazy-миграция на `find()`/`save()`.
- `BackfillMigrationRunner` — eager/backfill миграция таблицы `requisites_records`.
- `StaticTargetVersionResolver` — целевая версия схемы по `profile/selector`.

## CLI
Пакет регистрирует команду `requisites:migrate`.

Примеры:
- `php psb requisites:migrate --profile=company --dry-run`
- `php psb requisites:migrate --profile=company --selector=country:RU --from=1 --to=3 --batch-size=200`

## Profile Contract
- `RequisitesProfileInterface` — контракт profile-definition:
  - selector (`selectorKey/defaultSelector`);
  - схемы (`schemas`);
  - target versions + payload migrators;
  - `requestSchemaClasses` для request-level валидации;
  - `storageDefinition` для profile-routed storage.

## Built-in Catalogs And Forms
- `PhpSoftBox\Requisites\Country\RequisitesCountryCatalog`:
  - `countryCodes()`
  - `countryOptions()`
  - `defaultCountryCode()`
  - `normalizeCountryCode(...)`
- `PhpSoftBox\Requisites\Validation\Form\Company\CompanyRequisitesRuFormValidation`
- `PhpSoftBox\Requisites\Validation\Form\Company\CompanyRequisitesKzFormValidation`
- `PhpSoftBox\Requisites\Validation\Form\Company\CompanyRequisitesByFormValidation`
- `PhpSoftBox\Requisites\Validation\Form\Company\CompanyRequisitesAmFormValidation`
- `PhpSoftBox\Requisites\Validation\Form\Company\CompanyRequisitesAzFormValidation`
- `PhpSoftBox\Requisites\Validation\Form\Company\CompanyRequisitesGenericFormValidation`

Для зарубежных форм (`KZ/AM/AZ/BY`) текущий минимальный набор полей:
- `organization_type`
- `organization_name`
- `organization_inn`
- `organization_address`
- `bank_name`
- `bank_account_number`

## Built-in Rules By Country
- `Validation\Rule\Ru\*`:
  - `InnChecksumValidation`
  - `OgrnByTypeValidation`
  - `BankAccountChecksumValidation`
  - `KppFormatValidation`
  - `SnilsChecksumValidation`
- `Validation\Rule\Kz\*`:
  - `BinIinChecksumValidation`
  - `IbanFormatValidation`
- `Validation\Rule\By\*`:
  - `UnpFormatValidation`
  - `IbanFormatValidation`
- `Validation\Rule\Am\*`:
  - `TinFormatValidation`
  - (для банковских реквизитов в `AM` используется SWIFT/BIC + формат номера счета, без IBAN-правила в пакете)
- `Validation\Rule\Az\*`:
  - `VoenFormatValidation`
  - `IbanFormatValidation`
