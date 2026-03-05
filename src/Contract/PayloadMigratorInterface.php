<?php

declare(strict_types=1);

namespace PhpSoftBox\Requisites\Contract;

use PhpSoftBox\Requisites\Migration\RequisitesMigrationContext;

interface PayloadMigratorInterface
{
    public function supports(string $profile, string $selector, int $fromVersion, int $toVersion): bool;

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function migrate(array $payload, RequisitesMigrationContext $context): array;
}
