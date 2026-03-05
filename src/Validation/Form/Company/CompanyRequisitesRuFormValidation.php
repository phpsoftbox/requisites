<?php

declare(strict_types=1);

namespace PhpSoftBox\Requisites\Validation\Form\Company;

use PhpSoftBox\Requisites\Form\AbstractRequisitesFormValidation;
use PhpSoftBox\Requisites\Validation\Rule\Ru\BankAccountChecksumValidation;
use PhpSoftBox\Requisites\Validation\Rule\Ru\InnChecksumValidation;
use PhpSoftBox\Requisites\Validation\Rule\Ru\KppFormatValidation;
use PhpSoftBox\Requisites\Validation\Rule\Ru\OgrnByTypeValidation;
use PhpSoftBox\Validator\Filter\BooleanFilter;
use PhpSoftBox\Validator\Filter\LowercaseFilter;
use PhpSoftBox\Validator\Filter\NullIfEmptyFilter;
use PhpSoftBox\Validator\Filter\TrimFilter;
use PhpSoftBox\Validator\Filter\UppercaseFilter;
use PhpSoftBox\Validator\Rule\BoolValidation;
use PhpSoftBox\Validator\Rule\StringValidation;

use function is_array;
use function is_string;

final class CompanyRequisitesRuFormValidation extends AbstractRequisitesFormValidation
{
    public function beforeValidation(): void
    {
        $this->applyFilters(self::filtersDefinition());
    }

    public function rules(): array
    {
        return self::rulesDefinition();
    }

    public static function rulesDefinition(): array
    {
        return [
            'country_code' => [
                new StringValidation()->required()->in('RU'),
            ],
            'organization_type' => [
                new StringValidation()->required()->in('individual', 'legal'),
            ],
            'organization_name' => [
                new StringValidation()->required()->min(1)->max(255),
            ],
            'organization_inn' => [
                new StringValidation()->required()->max(12),
                new InnChecksumValidation(organizationTypeField: 'organization_type'),
            ],
            'organization_kpp' => [
                new StringValidation()
                    ->requiredIf(
                        static fn (mixed $context): bool => self::organizationType($context) === 'legal',
                        'organization_type=legal',
                    )
                    ->nullable(),
                new KppFormatValidation(),
            ],
            'organization_ogrn' => [
                new StringValidation()->required()->max(15),
                new OgrnByTypeValidation(organizationTypeField: 'organization_type'),
            ],
            'organization_address' => [
                new StringValidation()->required()->min(1)->max(500),
            ],
            'bank_name' => [
                new StringValidation()->required()->min(1)->max(255),
            ],
            'bank_bik' => [
                new StringValidation()->required()->regex('/^\d{9}$/'),
            ],
            'bank_corr_account' => [
                new StringValidation()->required()->max(20),
                new BankAccountChecksumValidation(bikField: 'bank_bik', mode: 'correspondent'),
            ],
            'bank_account_number' => [
                new StringValidation()->required()->max(20),
                new BankAccountChecksumValidation(bikField: 'bank_bik', mode: 'settlement'),
            ],
            'accountant_same_as_manager' => [
                new BoolValidation()->nullable(),
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
            'organization_name'          => $trim,
            'organization_inn'           => [new TrimFilter()],
            'organization_kpp'           => [new TrimFilter(), new UppercaseFilter(), new NullIfEmptyFilter()],
            'organization_ogrn'          => [new TrimFilter()],
            'organization_address'       => $trim,
            'bank_name'                  => $trim,
            'bank_bik'                   => [new TrimFilter()],
            'bank_corr_account'          => [new TrimFilter()],
            'bank_account_number'        => [new TrimFilter()],
            'accountant_same_as_manager' => [new BooleanFilter()],
        ];
    }

    private static function organizationType(mixed $context): ?string
    {
        $value = self::contextValue($context, 'organization_type');

        return is_string($value) ? $value : null;
    }

    private static function contextValue(mixed $context, string $key): mixed
    {
        if (is_array($context)) {
            return $context[$key] ?? null;
        }

        return null;
    }
}
