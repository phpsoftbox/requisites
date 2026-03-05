<?php

declare(strict_types=1);

namespace PhpSoftBox\Requisites\Tests\Storage;

use PhpSoftBox\Database\Database;
use PhpSoftBox\Database\SchemaBuilder\TableBlueprint;
use PhpSoftBox\Requisites\DTO\RequisitesRecord;
use PhpSoftBox\Requisites\DTO\RequisitesSubject;
use PhpSoftBox\Requisites\Storage\DefaultStorageAdapter;
use PhpSoftBox\Requisites\Tests\Support\IntegrationDatabases;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Throwable;

use function is_array;

#[CoversClass(DefaultStorageAdapter::class)]
final class DefaultStorageAdapterIntegrationTest extends TestCase
{
    /**
     * Проверяет: CRUD и upsert-логика адаптера работают на SQLite.
     */
    #[Test]
    public function sqliteCrudAndUpsertWork(): void
    {
        try {
            $database = IntegrationDatabases::sqliteDatabase();
        } catch (Throwable $exception) {
            self::markTestSkipped($exception->getMessage());
        }

        self::runCrudAndUpsertScenario($database, driver: 'sqlite');
    }

    /**
     * Проверяет: CRUD и upsert-логика адаптера работают на MariaDB.
     */
    #[Test]
    public function mariadbCrudAndUpsertWork(): void
    {
        try {
            $database = IntegrationDatabases::mariadbDatabase();
        } catch (Throwable $exception) {
            self::markTestSkipped($exception->getMessage());
        }

        self::runCrudAndUpsertScenario($database, driver: 'mariadb');
    }

    /**
     * Проверяет: CRUD и upsert-логика адаптера работают на Postgres.
     */
    #[Test]
    public function postgresCrudAndUpsertWork(): void
    {
        try {
            $database = IntegrationDatabases::postgresDatabase();
        } catch (Throwable $exception) {
            self::markTestSkipped($exception->getMessage());
        }

        self::runCrudAndUpsertScenario($database, driver: 'postgres');
    }

    /**
     * @param 'sqlite'|'mariadb'|'postgres' $driver
     */
    private static function runCrudAndUpsertScenario(Database $database, string $driver): void
    {
        self::prepareSchema($database, $driver);

        $adapter = new DefaultStorageAdapter($database->manager());
        $subject = new RequisitesSubject(type: 'company', id: 101);

        try {
            $draft = $adapter->create($subject, 'company');
            self::assertSame('company', $draft->profile);
            self::assertSame('default', $draft->selector);
            self::assertSame(1, $draft->schemaVersion);
            self::assertSame([], $draft->payload);
            self::assertSame([], $draft->attachments);

            self::assertNull($adapter->find($subject, 'company'));

            $adapter->save(new RequisitesRecord(
                profile: 'company',
                selector: 'country:RU',
                schemaVersion: 1,
                subjectType: $subject->type,
                subjectId: $subject->id,
                payload: [
                    'org_name' => 'ООО "Тест"',
                    'tags'     => [
                        'a',
                        'b',
                    ],
                ],
                attachments: [
                    'seal' => '/files/seal.png',
                ],
            ));

            $saved = $adapter->find($subject, 'company');
            self::assertNotNull($saved);
            self::assertSame('country:RU', $saved->selector);
            self::assertSame('ООО "Тест"', $saved->payload['org_name'] ?? null);
            self::assertSame(['a', 'b'], $saved->payload['tags'] ?? null);
            self::assertSame('/files/seal.png', $saved->attachments['seal'] ?? null);

            $adapter->save(new RequisitesRecord(
                profile: 'company',
                selector: 'country:RU',
                schemaVersion: 2,
                subjectType: $subject->type,
                subjectId: $subject->id,
                payload: [
                    'org_name' => 'ООО "Тест 2"',
                    'meta'     => [
                        'region' => 'RU-MOW',
                    ],
                ],
                attachments: [
                    'seal'      => '/files/seal-v2.png',
                    'signature' => '/files/sign-v2.png',
                ],
                id: null,
            ));

            $updatedByUnique = $adapter->find($subject, 'company');
            self::assertNotNull($updatedByUnique);
            self::assertSame(2, $updatedByUnique->schemaVersion);
            self::assertSame('ООО "Тест 2"', $updatedByUnique->payload['org_name'] ?? null);
            self::assertSame('/files/sign-v2.png', $updatedByUnique->attachments['signature'] ?? null);
            self::assertNotNull($updatedByUnique->id);

            $adapter->save(new RequisitesRecord(
                profile: $updatedByUnique->profile,
                selector: 'country:KZ',
                schemaVersion: 3,
                subjectType: $updatedByUnique->subjectType,
                subjectId: $updatedByUnique->subjectId,
                payload: ['org_name' => 'LLP Demo'],
                attachments: ['seal' => '/files/seal-kz.png'],
                id: $updatedByUnique->id,
            ));

            $updatedById = $adapter->find($subject, 'company');
            self::assertNotNull($updatedById);
            self::assertSame('country:KZ', $updatedById->selector);
            self::assertSame(3, $updatedById->schemaVersion);
            self::assertSame('LLP Demo', $updatedById->payload['org_name'] ?? null);

            $countRow = $database->fetchOne(
                'SELECT COUNT(*) AS cnt FROM requisites_records WHERE subject_type = :subject_type AND subject_id = :subject_id AND profile = :profile',
                [
                    'subject_type' => 'company',
                    'subject_id'   => '101',
                    'profile'      => 'company',
                ],
            );

            self::assertIsArray($countRow);
            self::assertSame(1, (int) ($countRow['cnt'] ?? 0));
        } finally {
            $database->execute('DROP TABLE IF EXISTS requisites_records');
        }
    }

    /**
     * @param 'sqlite'|'mariadb'|'postgres' $driver
     */
    private static function prepareSchema(Database $database, string $driver): void
    {
        $database->execute('DROP TABLE IF EXISTS requisites_records');

        $database->connection()->schema()->create('requisites_records', static function (TableBlueprint $table) use ($driver): void {
            if ($driver === 'mariadb') {
                $table->engine('InnoDB');
            }

            $table->id();
            $table->string('subject_type', 120);
            $table->string('subject_id', 64);
            $table->string('profile', 80);
            $table->string('selector', 120)->default('default');
            $table->json('payload_json')->nullable();
            $table->json('attachments_json')->nullable();
            $table->integer('schema_version')->default(1);
            $table->datetime('created_datetime');
            $table->datetime('updated_datetime');

            $table->unique(['subject_type', 'subject_id', 'profile'], 'requisites_subject_profile_unique');
            $table->index(['profile', 'selector'], 'requisites_profile_selector_index');
        });

        $row = $database->fetchOne('SELECT 1');
        self::assertTrue(is_array($row));
    }
}
