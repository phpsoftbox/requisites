# Requisites

`phpsoftbox/requisites` хранит изменяемые наборы реквизитов в JSON, но оставляет их типизированными на уровне приложения: для каждого набора известны форма, правила валидации, вариант схемы и версия структуры.

Компонент подходит не только для реквизитов компании. Его можно использовать для паспортных данных, заявлений, договоров и других наборов полей, состав которых зависит от страны, типа владельца или бизнес-сценария.

Минимальная версия PHP — 8.5.

## С чего начать

Документация рассчитана на последовательное чтение:

1. [01 — Первое подключение](docs/01-instructions.md) — основные понятия, таблица, форма, validator, профиль и полный цикл сохранения.
2. [02 — Managed lifecycle](docs/02-managed-lifecycle.md) — несколько профилей, selector policies, кастомный ORM storage, payload migrations и CLI backfill.
3. [03 — Обновление существующего проекта](docs/03-upgrade-notes.md) — поведенческие изменения, пошаговый перенос и production checklist.

Если вы впервые видите компонент, начните с главы 01. Глава 02 намеренно не повторяет базовые понятия, а глава 03 рассчитана на читателя, который сначала познакомился с текущей архитектурой.

## Какую проблему решает компонент

Представим, что у компании могут быть российские или казахстанские реквизиты. У этих вариантов разные поля и правила, а структура сохранённых данных со временем меняется.

Requisites добавляет поверх JSON:

- владельца данных — `subject`;
- назначение набора — `profile`;
- вариант схемы — `selector`;
- версию JSON — `schema version`;
- описание формы для клиента;
- server-side validation и фильтрацию;
- lazy migration и массовый backfill;
- маршрутизацию в общую таблицу или проектную ORM entity.

Стабильные доменные поля, которые участвуют в индексах и SQL-фильтрах, лучше хранить обычными колонками. Requisites предназначен для составных и изменяемых наборов данных.

## Рекомендуемый API

Для нового кода используется managed lifecycle:

```text
RequisitesProfileInterface
        ↓
ArrayRequisitesProfileRegistry
        ↓
ManagedRequisitesFactory
        ↓
DefaultRequisitesManager
```

Основной write-метод — `validateAndSave()`. Он:

- выбирает schema по profile и selector;
- выполняет form validation;
- не пишет данные при validation errors;
- сохраняет только `ValidationResult::filteredData()`;
- выставляет правильную schema version;
- безопасно обрабатывает конкурентное первое сохранение.

Низкоуровневые storage, schema, validation и migration classes остаются доступны для специализированной инфраструктуры и совместимости. При их прямом использовании orchestration и проверка payload становятся ответственностью приложения.

## Payload migrations

- `PayloadMigrationEngine` выполняет последовательные переходы `N → N+1`.
- `MigrationAwareStorageAdapter` лениво обновляет востребованные записи при чтении и сохранении.
- `BackfillMigrationRunner` массово обновляет storage выбранного профиля.
- `StaticTargetVersionResolver` определяет целевую версию по `profile + selector`.

Команда для предварительной проверки:

```bash
php psb requisites:migrate --profile=company --dry-run
```

Пример целевого backfill:

```bash
php psb requisites:migrate \
    --profile=company \
    --selector=country:RU \
    --from=1 \
    --to=3 \
    --batch-size=200
```

Перед запуском команды application container должен предоставлять `BackfillMigrationRunner`. Полная настройка описана в [главе 02](docs/02-managed-lifecycle.md#12-backfill-и-cli).

## Готовые формы и правила

Для company profile доступны:

- `PhpSoftBox\Requisites\Country\RequisitesCountryCatalog`;
- `CompanyRequisitesRuFormValidation`;
- `CompanyRequisitesKzFormValidation`;
- `CompanyRequisitesByFormValidation`;
- `CompanyRequisitesAmFormValidation`;
- `CompanyRequisitesAzFormValidation`;
- `CompanyRequisitesGenericFormValidation`.

Form validation classes находятся в `PhpSoftBox\Requisites\Validation\Form\Company`.

Страновые rules находятся в:

- `PhpSoftBox\Requisites\Validation\Rule\Ru`;
- `PhpSoftBox\Requisites\Validation\Rule\Kz`;
- `PhpSoftBox\Requisites\Validation\Rule\By`;
- `PhpSoftBox\Requisites\Validation\Rule\Am`;
- `PhpSoftBox\Requisites\Validation\Rule\Az`.

Готовый класс следует подключать только после проверки, что его поля и ограничения совпадают с контрактом приложения.

## Проверка компонента

Из корня монорепозитория:

```bash
make select-requisites
make php-test
make php-composer-cs-check
```
