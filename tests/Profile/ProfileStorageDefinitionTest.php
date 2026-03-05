<?php

declare(strict_types=1);

namespace PhpSoftBox\Requisites\Tests\Profile;

use PhpSoftBox\Requisites\Profile\ProfileStorageDefinition;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ProfileStorageDefinition::class)]
final class ProfileStorageDefinitionTest extends TestCase
{
    #[Test]
    public function createsOrmDefinition(): void
    {
        $definition = ProfileStorageDefinition::orm(
            entityClass: 'App\\Entity\\CompanyRequisites',
            connection: 'main',
            migrationAware: false,
        );

        self::assertSame('orm', $definition->driver);
        self::assertSame('main', $definition->connection);
        self::assertFalse($definition->migrationAware);
        self::assertSame('App\\Entity\\CompanyRequisites', $definition->entityClass);
    }

    #[Test]
    public function createsDefaultDefinition(): void
    {
        $definition = ProfileStorageDefinition::default(
            table: 'requisites_records',
            connection: 'default',
            migrationAware: true,
        );

        self::assertSame('default', $definition->driver);
        self::assertSame('requisites_records', $definition->table);
        self::assertTrue($definition->migrationAware);
    }
}
