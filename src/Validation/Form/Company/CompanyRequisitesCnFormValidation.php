<?php

declare(strict_types=1);

namespace PhpSoftBox\Requisites\Validation\Form\Company;

use PhpSoftBox\Requisites\Validation\Rule\Cn\UsccChecksumValidation;
use PhpSoftBox\Validator\Rule\StringValidation;

/**
 * Реквизиты компании Китая: международная схема, регистрационный номер — USCC (18 символов с контрольным).
 */
final class CompanyRequisitesCnFormValidation extends CompanyRequisitesInternationalFormValidation
{
    public static function rulesDefinition(?array $countryCodes = null): array
    {
        $rules = parent::rulesDefinition(['CN']);

        $rules['organization_inn'] = [
            new StringValidation()->required()->regex('/^[0-9A-Z]{18}$/'),
            new UsccChecksumValidation(),
        ];

        return $rules;
    }
}
