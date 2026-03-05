<?php

declare(strict_types=1);

namespace PhpSoftBox\Requisites\Tests\Storage;

use PhpSoftBox\Database\Database;
use PhpSoftBox\Database\SchemaBuilder\TableBlueprint;
use PhpSoftBox\Requisites\DTO\RequisitesRecord;
use PhpSoftBox\Requisites\DTO\RequisitesSubject;
use PhpSoftBox\Requisites\Migration\PayloadMigrationEngine;
use PhpSoftBox\Requisites\Migration\PayloadMigrationRegistry;
use PhpSoftBox\Requisites\Migration\StaticTargetVersionResolver;
use PhpSoftBox\Requisites\Storage\DefaultStorageAdapter;
use PhpSoftBox\Requisites\Storage\MigrationAwareStorageAdapter;
use PhpSoftBox\Requisites\Tests\Support\IntegrationDatabases;
use PhpSoftBox\Requisites\Tests\Support\StepMigrator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Throwable;

#[CoversClass(MigrationAwareStorageAdapter::class)]
final class MigrationAwareStorageAdapterIntegrationTest extends TestCase
{
    /**
     * Проверяет: lazy migration на чтении работает в SQLite.
     */
    #[Test]
    public function sqliteMigratesOnFind(): void
    {
        try {
            $database = IntegrationDatabases::sqliteDatabase();
        } catch (Throwable $exception) {
            self::markTestSkipped($exception->getMessage());
        }

        self::runScenario($database, driver: 'sqlite');
    }

    /**
     * Проверяет: lazy migration на чтении работает в MariaDB.
     */
    #[Test]
    public function mariadbMigratesOnFind(): void
    {
        try {
            $database = IntegrationDatabases::mariadbDatabase();
        } catch (Throwable $exception) {
            self::markTestSkipped($exception->getMessage());
        }

        self::runScenario($database, driver: 'mariadb');
    }

    /**
     * Проверяет: lazy migration на чтении работает в Postgres.
     */
    #[Test]
    public function postgresMigratesOnFind(): void
    {
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

        $subject = new RequisitesSubject('company', 901);
        $base    = new DefaultStorageAdapter($database->manager());

        $storage = new MigrationAwareStorageAdapter(
            inner: $base,
            migrationEngine: new PayloadMigrationEngine(new PayloadMigrationRegistry([
                new StepMigrator('company', 'country:RU', 1, 2, 'a'),
                new StepMigrator('company', 'country:RU', 2, 3, 'b'),
            ])),
            targetResolver: new StaticTargetVersionResolver([
                'company' => [
                    'country:RU' => 3,
                    'default'    => 1,
                ],
            ]),
        );

        try {
            $base->save(new RequisitesRecord(
                profile: 'company',
                selector: 'country:RU',
                schemaVersion: 1,
                subjectType: $subject->type,
                subjectId: $subject->id,
                payload: ['inn' => '1234567890'],
                attachments: ['seal' => '/files/seal-v1.png'],
            ));

            $migrated = $storage->find($subject, 'company');
            self::assertNotNull($migrated);
            self::assertSame(3, $migrated->schemaVersion);
            self::assertSame(['a', 'b'], $migrated->payload['steps'] ?? null);

            $persisted = $base->find($subject, 'company');
            self::assertNotNull($persisted);
            self::assertSame(3, $persisted->schemaVersion);
            self::assertSame(['a', 'b'], $persisted->payload['steps'] ?? null);
            self::assertNotNull($persisted->id);

            $storage->save(new RequisitesRecord(
                profile: 'company',
                selector: 'country:RU',
                schemaVersion: 1,
                subjectType: $subject->type,
                subjectId: $subject->id,
                payload: ['inn' => '9999999999'],
                attachments: ['seal' => '/files/seal-v2.png'],
                id: $persisted->id,
            ));

            $saved = $base->find($subject, 'company');
            self::assertNotNull($saved);
            self::assertSame(3, $saved->schemaVersion);
            self::assertSame(['a', 'b'], $saved->payload['steps'] ?? null);
            self::assertSame('/files/seal-v2.png', $saved->attachments['seal'] ?? null);

            $draft = $storage->create(new RequisitesSubject('company', 902), 'company');
            self::assertSame(1, $draft->schemaVersion);
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
    }
}
