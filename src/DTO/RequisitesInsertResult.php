<?php

declare(strict_types=1);

namespace PhpSoftBox\Requisites\DTO;

final readonly class RequisitesInsertResult
{
    public function __construct(
        public RequisitesRecord $record,
        public bool $inserted,
    ) {
    }
}
