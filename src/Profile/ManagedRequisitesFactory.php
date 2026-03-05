<?php

declare(strict_types=1);

namespace PhpSoftBox\Requisites\Profile;

use PhpSoftBox\Database\Connection\ConnectionManagerInterface;
use PhpSoftBox\Requisites\Contract\RequisitesProfileRegistryInterface;
use PhpSoftBox\Requisites\DefaultRequisitesManager;
use PhpSoftBox\Requisites\Migration\BackfillMigrationRunner;
use PhpSoftBox\Requisites\Migration\PayloadMigrationEngine;
use PhpSoftBox\Requisites\Schema\SchemaFieldResolver;
use PhpSoftBox\Requisites\Schema\SelectorResolutionPolicy;
use PhpSoftBox\Requisites\Storage\ProfileStorageAdapterFactory;

final readonly class ManagedRequisitesFactory
{
    public function __construct(
        private ConnectionManagerInterface $connections,
    ) {
    }

    /**
     * @param array<string, SelectorResolutionPolicy> $selectorPolicies
     */
    public function createManager(
        RequisitesProfileRegistryInterface $profiles,
        array $selectorPolicies = [],
    ): DefaultRequisitesManager {
        $migrationRegistry = new ProfileMigrationRegistryFactory()->create($profiles);

        $migrationEngine = new PayloadMigrationEngine($migrationRegistry);
        $targetResolver  = new ProfileTargetVersionResolverFactory()->create($profiles);

        $storageFactory = new ProfileStorageAdapterFactory(
            connections: $this->connections,
            migrationEngine: $migrationEngine,
            targetVersionResolver: $targetResolver,
        );

        return new DefaultRequisitesManager(
            profiles: $profiles,
            storage: $storageFactory->createRouter($profiles),
            selectorResolver: new ProfileSelectorResolverFactory()->create($profiles, $selectorPolicies),
            schemaProvider: new ProfileSchemaProviderFactory()->create($profiles),
            schemaFieldResolver: new SchemaFieldResolver(),
            validator: new ProfileValidatorFactory()->create($profiles),
            migrationEngine: $migrationEngine,
            targetVersionResolver: $targetResolver,
        );
    }

    public function createBackfillRunner(
        RequisitesProfileRegistryInterface $profiles,
    ): BackfillMigrationRunner {
        $migrationRegistry = new ProfileMigrationRegistryFactory()->create($profiles);

        $migrationEngine = new PayloadMigrationEngine($migrationRegistry);
        $targetResolver  = new ProfileTargetVersionResolverFactory()->create($profiles);

        $storageFactory = new ProfileStorageAdapterFactory(
            connections: $this->connections,
            migrationEngine: $migrationEngine,
            targetVersionResolver: $targetResolver,
        );

        return new BackfillMigrationRunner(
            connections: $storageFactory->createRouter($profiles),
            engine: $migrationEngine,
            targetResolver: $targetResolver,
        );
    }
}
