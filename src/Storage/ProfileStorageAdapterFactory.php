<?php

declare(strict_types=1);

namespace PhpSoftBox\Requisites\Storage;

use PhpSoftBox\Database\Connection\ConnectionManagerInterface;
use PhpSoftBox\Requisites\Contract\RequisitesProfileRegistryInterface;
use PhpSoftBox\Requisites\Contract\StorageAdapterFactoryInterface;
use PhpSoftBox\Requisites\Contract\StorageAdapterInterface;
use PhpSoftBox\Requisites\Exception\InvalidProfileStorageDefinitionException;
use PhpSoftBox\Requisites\Migration\MigrationTargetVersionResolverInterface;
use PhpSoftBox\Requisites\Migration\PayloadMigrationEngine;
use PhpSoftBox\Requisites\Profile\ProfileStorageDefinition;

use function class_exists;

final readonly class ProfileStorageAdapterFactory implements StorageAdapterFactoryInterface
{
    public function __construct(
        private ConnectionManagerInterface $connections,
        private PayloadMigrationEngine $migrationEngine,
        private MigrationTargetVersionResolverInterface $targetVersionResolver,
    ) {
    }

    public function create(ProfileStorageDefinition $definition): StorageAdapterInterface
    {
        if ($definition->driver === 'orm'
            && ($definition->entityClass === null || !class_exists($definition->entityClass))
        ) {
            throw new InvalidProfileStorageDefinitionException('ORM storage entity class does not exist.');
        }

        $adapter = match ($definition->driver) {
            'default' => new DefaultStorageAdapter(
                connections: $this->connections,
                connectionName: $definition->connection,
                table: $definition->table
                    ?? throw new InvalidProfileStorageDefinitionException('Default storage table is not configured.'),
            ),
            'orm' => new OrmEntityStorageAdapter(
                connections: $this->connections,
                entityClass: $definition->entityClass
                    ?? throw new InvalidProfileStorageDefinitionException('ORM storage entity class is not configured.'),
                connectionName: $definition->connection,
                fieldMap: OrmEntityFieldMap::fromArray($definition->ormFieldMap),
            ),
            default => throw new InvalidProfileStorageDefinitionException('Unknown profile storage driver.'),
        };

        if (!$definition->migrationAware) {
            return $adapter;
        }

        return new MigrationAwareStorageAdapter(
            inner: $adapter,
            migrationEngine: $this->migrationEngine,
            targetResolver: $this->targetVersionResolver,
        );
    }

    public function createRouter(
        RequisitesProfileRegistryInterface $registry,
        ?string $fallbackProfile = null,
    ): ProfileRouterStorageAdapter {
        $adapters = [];
        foreach ($registry->all() as $name => $profile) {
            $adapters[$name] = $this->create($profile->storageDefinition());
        }

        return new ProfileRouterStorageAdapter($adapters, $fallbackProfile);
    }
}
