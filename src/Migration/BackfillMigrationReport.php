<?php

declare(strict_types=1);

namespace PhpSoftBox\Requisites\Migration;

final readonly class BackfillMigrationReport
{
    /**
     * @param list<string> $errors
     */
    public function __construct(
        public int $processed,
        public int $migrated,
        public int $skipped,
        public int $failed,
        public array $errors = [],
    ) {
    }

    public function hasFailures(): bool
    {
        return $this->failed > 0;
    }
}
