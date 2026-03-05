<?php

declare(strict_types=1);

namespace PhpSoftBox\Requisites\Profile;

use PhpSoftBox\Requisites\Contract\RequisitesProfileRegistryInterface;
use PhpSoftBox\Requisites\Migration\PayloadMigrationRegistry;

final readonly class ProfileMigrationRegistryFactory
{
    public function create(RequisitesProfileRegistryInterface $registry): PayloadMigrationRegistry
    {
        $migrators = [];
        foreach ($registry->all() as $profile) {
            foreach ($profile->migrators() as $migrator) {
                $migrators[] = $migrator;
            }
        }

        return new PayloadMigrationRegistry($migrators);
    }
}
