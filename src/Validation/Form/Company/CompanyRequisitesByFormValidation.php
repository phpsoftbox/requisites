<?php

declare(strict_types=1);

namespace PhpSoftBox\Requisites\Validation\Form\Company;

use PhpSoftBox\Requisites\Form\AbstractRequisitesFormValidation;
use PhpSoftBox\Requisites\Validation\Rule\By\IbanFormatValidation;
use PhpSoftBox\Requisites\Validation\Rule\By\UnpFormatValidation;
use PhpSoftBox\Validator\Filter\LowercaseFilter;
use PhpSoftBox\Validator\Filter\TrimFilter;
use PhpSoftBox\Validator\Filter\UppercaseFilter;
use PhpSoftBox\Validator\Rule\StringValidation;

final class CompanyRequisitesByFormValidation extends AbstractRequisitesFormValidation
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
                new StringValidation()->required()->in('BY'),
            ],
            'organization_type' => [
                new StringValidation()->required()->in('individual', 'legal'),
            ],
            'organization_name' => [
                new StringValidation()->required()->min(1)->max(255),
            ],
            'organization_inn' => [
                new StringValidation()->required(),
                new UnpFormatValidation(),
            ],
            'organization_address' => [
                new StringValidation()->required()->min(1)->max(500),
            ],
            'bank_name' => [
                new StringValidation()->required()->min(1)->max(255),
            ],
            'bank_account_number' => [
                new StringValidation()->required(),
                new IbanFormatValidation(),
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
            'country_code'         => [new TrimFilter(), new UppercaseFilter()],
            'organization_type'    => [new TrimFilter(), new LowercaseFilter()],
            'organization_name'    => $trim,
            'organization_inn'     => [new TrimFilter()],
            'organization_address' => $trim,
            'bank_name'            => $trim,
            'bank_account_number'  => [new TrimFilter(), new UppercaseFilter()],
        ];
    }
}
