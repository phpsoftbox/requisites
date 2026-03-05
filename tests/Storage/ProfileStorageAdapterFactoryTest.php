<?php

declare(strict_types=1);

namespace PhpSoftBox\Requisites\Tests\Storage;

use PhpSoftBox\Database\Connection\ConnectionManagerInterface;
use PhpSoftBox\Requisites\Exception\InvalidFieldMapException;
use PhpSoftBox\Requisites\Migration\PayloadMigrationEngine;
use PhpSoftBox\Requisites\Migration\PayloadMigrationRegistry;
use PhpSoftBox\Requisites\Migration\StaticTargetVersionResolver;
use PhpSoftBox\Requisites\Profile\ProfileStorageDefinition;
use PhpSoftBox\Requisites\Storage\DefaultStorageAdapter;
use PhpSoftBox\Requisites\Storage\MigrationAwareStorageAdapter;
use PhpSoftBox\Requisites\Storage\OrmEntityStorageAdapter;
use PhpSoftBox\Requisites\Storage\ProfileStorageAdapterFactory;
use PhpSoftBox\Requisites\Tests\Storage\Fixtures\CompanyRequisitesEntity;
use PhpSoftBox\Requisites\Tests\Support\IntegrationDatabases;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Throwable;

#[CoversClass(ProfileStorageAdapterFactory::class)]
final class ProfileStorageAdapterFactoryTest extends TestCase
{
    #[Test]
    public function buildsDefaultOrmAndMigrationAwareAdapters(): void
    {
        try {
            $database = IntegrationDatabases::sqliteDatabase();
        } catch (Throwable $exception) {
            self::markTestSkipped($exception->getMessage());
        }

        $factory = self::factory($database->manager());

        self::assertInstanceOf(DefaultStorageAdapter::class, $factory->create(
            ProfileStorageDefinition::default(migrationAware: false),
        ));
        self::assertInstanceOf(OrmEntityStorageAdapter::class, $factory->create(
            ProfileStorageDefinition::orm(
                CompanyRequisitesEntity::class,
                migrationAware: false,
                ormFieldMap: ['selectorProperty' => 'countryCode'],
            ),
        ));
        self::assertInstanceOf(MigrationAwareStorageAdapter::class, $factory->create(
            ProfileStorageDefinition::default(migrationAware: true),
        ));
    }

    #[Test]
    public function rejectsUnknownFieldMapKeyDuringBuild(): void
    {
        try {
            $database = IntegrationDatabases::sqliteDatabase();
        } catch (Throwable $exception) {
            self::markTestSkipped($exception->getMessage());
        }

        $this->expectException(InvalidFieldMapException::class);

        self::factory($database->manager())->create(ProfileStorageDefinition::orm(
            CompanyRequisitesEntity::class,
            migrationAware: false,
            ormFieldMap: ['unknownProperty' => 'value'],
        ));
    }

    private static function factory(ConnectionManagerInterface $connections): ProfileStorageAdapterFactory
    {
        return new ProfileStorageAdapterFactory(
            connections: $connections,
            migrationEngine: new PayloadMigrationEngine(new PayloadMigrationRegistry()),
            targetVersionResolver: new StaticTargetVersionResolver(['company' => 1]),
        );
    }
}
