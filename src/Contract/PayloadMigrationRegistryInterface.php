<?php

declare(strict_types=1);

namespace PhpSoftBox\Requisites\Contract;

interface PayloadMigrationRegistryInterface
{
    /**
     * @return list<PayloadMigratorInterface>
     */
    public function chain(string $profile, string $selector, int $fromVersion, int $toVersion): array;
}
