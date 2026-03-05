<?php

declare(strict_types=1);

namespace PhpSoftBox\Requisites\Profile;

use PhpSoftBox\Requisites\Contract\RequisitesProfileRegistryInterface;
use PhpSoftBox\Requisites\Validation\FormValidationRequisitesValidator;

final readonly class ProfileValidatorFactory
{
    public function create(RequisitesProfileRegistryInterface $registry): FormValidationRequisitesValidator
    {
        $forms = [];
        foreach ($registry->all() as $name => $profile) {
            $forms[$name] = $profile->formValidationClasses();
        }

        return new FormValidationRequisitesValidator($forms, strict: true);
    }
}
