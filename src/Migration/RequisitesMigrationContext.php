<?php

declare(strict_types=1);

namespace PhpSoftBox\Requisites\Migration;

final readonly class RequisitesMigrationContext
{
    public function __construct(
        public string $profile,
        public string $selector,
        public int $fromVersion,
        public int $toVersion,
        public int|string|null $recordId = null,
    ) {
    }
}
