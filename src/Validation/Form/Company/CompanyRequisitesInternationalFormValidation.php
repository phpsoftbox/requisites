<?php

declare(strict_types=1);

namespace PhpSoftBox\Requisites\Validation\Form\Company;

use BackedEnum;
use PhpSoftBox\Filter\LowercaseFilter;
use PhpSoftBox\Filter\TrimFilter;
use PhpSoftBox\Filter\UppercaseFilter;
use PhpSoftBox\Requisites\Country\OrganizationTypesEnum;
use PhpSoftBox\Requisites\Country\VatModesEnum;
use PhpSoftBox\Requisites\Form\AbstractRequisitesFormValidation;
use PhpSoftBox\Validator\Rule\StringValidation;

use function array_map;

/**
 * Международные реквизиты компании: значения на английском обязательны и лежат в общих ключах
 * (`organization_name`, `organization_address`, регистрационный номер — `organization_inn`), чтобы документы читали их
 * так же, как реквизиты любой страны; значения на родном языке (`*_local`) и банк — по желанию.
 *
 * Страна добавляет свои проверки поверх {@see self::rulesDefinition()} (см. {@see CompanyRequisitesCnFormValidation}).
 */
class CompanyRequisitesInternationalFormValidation extends AbstractRequisitesFormValidation
{
    public function beforeValidation(): void
    {
        $this->applyFilters(static::filtersDefinition());
    }

    public function rules(): array
    {
        return static::rulesDefinition();
    }

    /**
     * @param list<string>|null $countryCodes Допустимые страны; null — любая двухбуквенная
     */
    public static function rulesDefinition(?array $countryCodes = null): array
    {
        $country = new StringValidation()->required();

        return [
            'country_code'      => [$countryCodes !== null ? $country->in(...$countryCodes) : $country->regex('/^[A-Z]{2}$/')],
            'organization_type' => [
                new StringValidation()->required()->in(...self::values(OrganizationTypesEnum::cases())),
            ],
            'vat_mode' => [
                new StringValidation()->required()->in(...self::values(VatModesEnum::cases())),
            ],
            'organization_inn' => [
                new StringValidation()->required()->min(3)->max(64),
            ],
            'organization_name' => [
                new StringValidation()->required()->min(1)->max(255),
            ],
            'organization_name_local' => [
                new StringValidation()->nullable()->max(255),
            ],
            'representative_name' => [
                new StringValidation()->required()->min(1)->max(255),
            ],
            'representative_name_local' => [
                new StringValidation()->nullable()->max(255),
            ],
            'organization_address' => [
                new StringValidation()->required()->min(1)->max(500),
            ],
            'organization_address_local' => [
                new StringValidation()->nullable()->max(500),
            ],
            'bank_name' => [
                new StringValidation()->nullable()->max(255),
            ],
            'bank_swift' => [
                new StringValidation()->nullable()->regex('/^[A-Z]{6}[A-Z0-9]{2}([A-Z0-9]{3})?$/'),
            ],
            'bank_account_number' => [
                new StringValidation()->nullable()->max(64),
            ],
        ];
    }

    /**
     * @return array<string, callable(mixed): mixed|list<callable(mixed): mixed>>
     */
    public static function filtersDefinition(): array
    {
        $trim = [new TrimFilter()];

        return [
            'country_code'               => [new TrimFilter(), new UppercaseFilter()],
            'organization_type'          => [new TrimFilter(), new LowercaseFilter()],
            'vat_mode'                   => [new TrimFilter(), new LowercaseFilter()],
            'organization_inn'           => [new TrimFilter(), new UppercaseFilter()],
            'organization_name'          => $trim,
            'organization_name_local'    => $trim,
            'representative_name'        => $trim,
            'representative_name_local'  => $trim,
            'organization_address'       => $trim,
            'organization_address_local' => $trim,
            'bank_name'                  => $trim,
            'bank_swift'                 => [new TrimFilter(), new UppercaseFilter()],
            'bank_account_number'        => $trim,
        ];
    }

    /**
     * @param list<BackedEnum> $cases
     *
     * @return list<string>
     */
    private static function values(array $cases): array
    {
        return array_map(static fn (BackedEnum $case): string => (string) $case->value, $cases);
    }
}
