<?php

declare(strict_types=1);

namespace PhpSoftBox\Requisites\Contract;

use PhpSoftBox\Validator\ValidationResult;

interface RequisitesValidatorInterface
{
    /**
     * @param array<string, mixed> $payload
     */
    public function validate(string $profile, string $selector, array $payload): ValidationResult;
}
