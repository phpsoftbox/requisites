<?php

declare(strict_types=1);

namespace PhpSoftBox\Requisites\Tests\Storage;

use PhpSoftBox\Database\Database;
use PhpSoftBox\Database\SchemaBuilder\TableBlueprint;
use PhpSoftBox\Orm\Repository\AutoEntityMapper;
use PhpSoftBox\Requisites\DTO\RequisitesRecord;
use PhpSoftBox\Requisites\DTO\RequisitesSubject;
use PhpSoftBox\Requisites\Storage\OrmEntityFieldMap;
use PhpSoftBox\Requisites\Storage\OrmEntityStorageAdapter;
use PhpSoftBox\Requisites\Tests\Storage\Fixtures\CompanyRequisitesEntity;
use PhpSoftBox\Requisites\Tests\Support\IntegrationDatabases;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionNamedType;
use Throwable;

use function is_array;

#[CoversClass(OrmEntityStorageAdapter::class)]
final class OrmEntityStorageAdapterIntegrationTest extends TestCase
{
    /**
     * Интеграционный тест: adapter работает на SQLite с кастомным discriminator column (country_code).
     */
    #[Test]
    public function sqliteAdapterWorksWithCustomSelectorColumn(): void
    {
        if (!self::supportsDataCastingMapper()) {
            self::markTestSkipped('Requires phpsoftbox/orm with DataCasting mapper contract.');
        }

        try {
            $database = IntegrationDatabases::sqliteDatabase();
        } catch (Throwable $exception) {
            self::markTestSkipped($exception->getMessage());
        }

        self::runScenario($database, driver: 'sqlite');
    }

    /**
     * Интеграционный тест: adapter работает на MariaDB.
     */
    #[Test]
    public function mariadbAdapterWorksWithCustomSelectorColumn(): void
    {
        if (!self::supportsDataCastingMapper()) {
            self::markTestSkipped('Requires phpsoftbox/orm with DataCasting mapper contract.');
        }

        try {
            $database = IntegrationDatabases::mariadbDatabase();
        } catch (Throwable $exception) {
            self::markTestSkipped($exception->getMessage());
        }

        self::runScenario($database, driver: 'mariadb');
    }

    /**
     * Интеграционный тест: adapter работает на Postgres.
     */
    #[Test]
    public function postgresAdapterWorksWithCustomSelectorColumn(): void
    {
        if (!self::supportsDataCastingMapper()) {
            self::markTestSkipped('Requires phpsoftbox/orm with DataCasting mapper contract.');
        }

        try {
            $database = IntegrationDatabases::postgresDatabase();
        } catch (Throwable $exception) {
            self::markTestSkipped($exception->getMessage());
        }

        self::runScenario($database, driver: 'postgres');
    }

    /**
     * @param 'sqlite'|'mariadb'|'postgres' $driver
     */
    private static function runScenario(Database $database, string $driver): void
    {
        self::prepareSchema($database, $driver);

        $adapter = new OrmEntityStorageAdapter(
            connections: $database->manager(),
            entityClass: CompanyRequisitesEntity::class,
            fieldMap: new OrmEntityFieldMap(
                selectorProperty: 'countryCode',
            ),
        );
        $subject = new RequisitesSubject('company', 401);

        try {
            self::assertNull($adapter->find($subject, 'company'));

            $adapter->save(new RequisitesRecord(
                profile: 'company',
                selector: 'country:RU',
                schemaVersion: 1,
                subjectType: $subject->type,
                subjectId: $subject->id,
                payload: ['inn' => '1234567890'],
                attachments: ['seal' => '/files/seal.png'],
            ));

            $saved = $adapter->find($subject, 'company');
            self::assertNotNull($saved);
            self::assertSame('country:RU', $saved->selector);
            self::assertSame('1234567890', $saved->payload['inn'] ?? null);

            $adapter->save(new RequisitesRecord(
                profile: 'company',
                selector: 'template:v2',
                schemaVersion: 2,
                subjectType: $subject->type,
                subjectId: $subject->id,
                payload: ['inn' => '9876543210'],
                attachments: ['seal' => '/files/seal-v2.png'],
                id: null,
            ));

            $updatedByUnique = $adapter->find($subject, 'company');
            self::assertNotNull($updatedByUnique);
            self::assertSame(2, $updatedByUnique->schemaVersion);
            self::assertSame('template:v2', $updatedByUnique->selector);
            self::assertNotNull($updatedByUnique->id);

            $adapter->save(new RequisitesRecord(
                profile: 'company',
                selector: 'country:KZ',
                schemaVersion: 3,
                subjectType: $subject->type,
                subjectId: $subject->id,
                payload: ['inn' => '1111111111'],
                attachments: ['seal' => '/files/seal-kz.png'],
                id: $updatedByUnique->id,
            ));

            $updatedById = $adapter->find($subject, 'company');
            self::assertNotNull($updatedById);
            self::assertSame('country:KZ', $updatedById->selector);
            self::assertSame(3, $updatedById->schemaVersion);

            $countRow = $database->fetchOne(
                'SELECT COUNT(*) AS cnt FROM company_requisites WHERE subject_type = :subject_type AND subject_id = :subject_id AND profile = :profile',
                [
                    'subject_type' => 'company',
                    'subject_id'   => '401',
                    'profile'      => 'company',
                ],
            );
            self::assertIsArray($countRow);
            self::assertSame(1, (int) ($countRow['cnt'] ?? 0));

            $selectorRow = $database->fetchOne(
                'SELECT country_code FROM company_requisites WHERE subject_type = :subject_type AND subject_id = :subject_id AND profile = :profile LIMIT 1',
                [
                    'subject_type' => 'company',
                    'subject_id'   => '401',
                    'profile'      => 'company',
                ],
            );
            self::assertIsArray($selectorRow);
            self::assertSame('country:KZ', $selectorRow['country_code'] ?? null);
        } finally {
            $database->execute('DROP TABLE IF EXISTS company_requisites');
        }
    }

    /**
     * @param 'sqlite'|'mariadb'|'postgres' $driver
     */
    private static function prepareSchema(Database $database, string $driver): void
    {
        $database->execute('DROP TABLE IF EXISTS company_requisites');

        $database->connection()->schema()->create('company_requisites', static function (TableBlueprint $table) use ($driver): void {
            if ($driver === 'mariadb') {
                $table->engine('InnoDB');
            }

            $table->id();
            $table->string('subject_type', 120);
            $table->string('subject_id', 64);
            $table->string('profile', 80);
            $table->string('country_code', 32)->default('default');
            $table->json('payload_json')->nullable();
            $table->json('attachments_json')->nullable();
            $table->integer('schema_version')->default(1);
            $table->datetime('created_datetime');
            $table->datetime('updated_datetime');

            $table->unique(['subject_type', 'subject_id', 'profile'], 'company_requisites_subject_profile_unique');
            $table->index(['profile', 'country_code'], 'company_requisites_profile_country_index');
        });

        $row = $database->fetchOne('SELECT 1');
        self::assertTrue(is_array($row));
    }

    private static function supportsDataCastingMapper(): bool
    {
        $constructor = new ReflectionClass(AutoEntityMapper::class)->getConstructor();
        $casterType  = $constructor?->getParameters()[1]?->getType();
        $typeName    = $casterType instanceof ReflectionNamedType ? $casterType->getName() : null;

        return $typeName === 'PhpSoftBox\\DataCasting\\Contracts\\TypeCasterInterface';
    }
}
