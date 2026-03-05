<?php

declare(strict_types=1);

namespace PhpSoftBox\Requisites\DTO;

final readonly class RequisitesSubject
{
    public function __construct(
        public string $type,
        public int|string $id,
    ) {
    }
}
