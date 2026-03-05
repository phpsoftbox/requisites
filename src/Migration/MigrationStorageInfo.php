<?php

declare(strict_types=1);

namespace PhpSoftBox\Requisites\Migration;

final readonly class MigrationStorageInfo
{
    public function __construct(
        public string $driver,
        public string $connection,
        public string $table,
    ) {
    }
}
