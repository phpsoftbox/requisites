<?php

declare(strict_types=1);

namespace PhpSoftBox\Requisites\Exception;

use PhpSoftBox\Validator\ValidationResult;
use RuntimeException;

final class RequisitesValidationFailedException extends RuntimeException
{
    public function __construct(
        public readonly ValidationResult $validationResult,
    ) {
        parent::__construct('Requisites payload validation failed.');
    }
}
