<?php

declare(strict_types=1);

namespace PhpSoftBox\Requisites\Migration;

use PhpSoftBox\Requisites\Contract\RequisitesProfileRegistryInterface;
use PhpSoftBox\Requisites\Storage\ProfileStorageAdapterFactory;

final readonly class BackfillMigrationRunnerFactory
{
    public function __construct(
        private ProfileStorageAdapterFactory $storageFactory,
        private PayloadMigrationEngine $engine,
        private MigrationTargetVersionResolverInterface $targetResolver,
    ) {
    }

    public function create(RequisitesProfileRegistryInterface $profiles): BackfillMigrationRunner
    {
        return new BackfillMigrationRunner(
            connections: $this->storageFactory->createRouter($profiles),
            engine: $this->engine,
            targetResolver: $this->targetResolver,
        );
    }
}
