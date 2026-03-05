# Upgrade Notes

Краткие заметки по переходу на новую интеграцию `Requisites` с form-validation.

## Что изменилось

- Профильный контракт используется из пакета:
  - `PhpSoftBox\Requisites\Contract\RequisitesProfileInterface`
- Profile storage definition используется из пакета:
  - `PhpSoftBox\Requisites\Profile\ProfileStorageDefinition`
- Router storage adapter используется из пакета:
  - `PhpSoftBox\Requisites\Storage\ProfileRouterStorageAdapter`
- Валидация реквизитов через form-классы:
  - `PhpSoftBox\Requisites\Validation\FormValidationRequisitesValidator`
- Базовый класс для профилей форм реквизитов:
  - `PhpSoftBox\Requisites\Form\AbstractRequisitesFormValidation`
- Результат `RequisitesValidatorInterface::validate(...)` теперь:
  - `PhpSoftBox\Validator\ValidationResult`
  - вместо legacy `PhpSoftBox\Requisites\Validation\ValidationResult`

## Миграция приложения

1. Перенесите app-level profile/storage interfaces на пакетные классы.
2. Для каждого profile верните `formValidationClasses()` с map по selector.
3. Перенесите фильтры в `beforeValidation()` и применяйте через `applyFilters(...)`.
4. Подключите `FormValidationRequisitesValidator` в DI.
5. Удалите дублирующие app-level адаптеры/интерфейсы, если они больше не используются.

### Готовые классы для company-profile

- `PhpSoftBox\Requisites\Country\RequisitesCountryCatalog` — единый каталог поддерживаемых стран.
- `PhpSoftBox\Requisites\Validation\Form\Company\CompanyRequisitesRuFormValidation` — правила для `country:RU`.
- `PhpSoftBox\Requisites\Validation\Form\Company\CompanyRequisitesKzFormValidation` — правила для `country:KZ`.
- `PhpSoftBox\Requisites\Validation\Form\Company\CompanyRequisitesByFormValidation` — правила для `country:BY`.
- `PhpSoftBox\Requisites\Validation\Form\Company\CompanyRequisitesAmFormValidation` — правила для `country:AM`.
- `PhpSoftBox\Requisites\Validation\Form\Company\CompanyRequisitesAzFormValidation` — правила для `country:AZ`.
- `PhpSoftBox\Requisites\Validation\Form\Company\CompanyRequisitesGenericFormValidation` — fallback для `country:KZ|AM|AZ|BY`.

Для зарубежных стран (`KZ/AM/AZ/BY`) формы упрощены до минимального набора:
- `organization_type`
- `organization_name`
- `organization_inn`
- `organization_address`
- `bank_name`
- `bank_account_number`

### Namespace правил

Страновые rule-классы разнесены по подпапкам:
- `PhpSoftBox\Requisites\Validation\Rule\Ru\...`
- `PhpSoftBox\Requisites\Validation\Rule\Kz\...`
- `PhpSoftBox\Requisites\Validation\Rule\By\...`
- `PhpSoftBox\Requisites\Validation\Rule\Am\...`
- `PhpSoftBox\Requisites\Validation\Rule\Az\...`

## Важно про зависимости

Если приложение использует опубликованные пакеты `dev-master`, убедитесь, что версия `phpsoftbox/validator` содержит:

- `PhpSoftBox\Validator\AbstractFormValidation`
- `PhpSoftBox\Validator\Support\FilterPayloadApplier`

Без этих классов `RequestSchema`/`AbstractRequisitesFormValidation` не инициализируются.
