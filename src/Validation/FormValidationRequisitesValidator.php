<?php

declare(strict_types=1);

namespace PhpSoftBox\Requisites\Validation;

use PhpSoftBox\Requisites\Contract\RequisitesValidatorInterface;
use PhpSoftBox\Validator\FormValidationInterface;
use PhpSoftBox\Validator\ValidationResult;

use function is_array;
use function is_string;

final readonly class FormValidationRequisitesValidator implements RequisitesValidatorInterface
{
    /**
     * @param array<string, array<string, class-string<FormValidationInterface>>> $formClassesByProfile
     */
    public function __construct(
        private array $formClassesByProfile = [],
    ) {
    }

    public function validate(string $profile, string $selector, array $payload): ValidationResult
    {
        $formClass = $this->formClassFor($profile, $selector);
        if ($formClass === null) {
            return new ValidationResult([], $payload);
        }

        return new $formClass($payload)->validationResult();
    }

    /**
     * @return class-string<FormValidationInterface>|null
     */
    private function formClassFor(string $profile, string $selector): ?string
    {
        $schemaMap = $this->formClassesByProfile[$profile] ?? null;
        if (!is_array($schemaMap)) {
            return null;
        }

        $exact = $schemaMap[$selector] ?? null;
        if (is_string($exact) && $exact !== '') {
            return $exact;
        }

        $fallback = $schemaMap['default'] ?? null;
        if (is_string($fallback) && $fallback !== '') {
            return $fallback;
        }

        return null;
    }
}
