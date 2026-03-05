<?php

declare(strict_types=1);

namespace PhpSoftBox\Requisites\DTO;

use PhpSoftBox\Validator\ValidationResult;

final readonly class RequisitesSaveResult
{
    public function __construct(
        public RequisitesRecord $record,
        public ValidationResult $validation,
        public bool $saved,
    ) {
    }
}
