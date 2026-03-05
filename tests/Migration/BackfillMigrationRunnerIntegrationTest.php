<?php

declare(strict_types=1);

namespace PhpSoftBox\Requisites\Tests\Migration;

use JsonException;
use PhpSoftBox\Database\Database;
use PhpSoftBox\Database\SchemaBuilder\TableBlueprint;
use PhpSoftBox\Requisites\DTO\RequisitesRecord;
use PhpSoftBox\Requisites\Migration\BackfillMigrationRunner;
use PhpSoftBox\Requisites\Migration\PayloadMigrationEngine;
use PhpSoftBox\Requisites\Migration\PayloadMigrationRegistry;
use PhpSoftBox\Requisites\Migration\StaticTargetVersionResolver;
use PhpSoftBox\Requisites\Storage\DefaultStorageAdapter;
use PhpSoftBox\Requisites\Tests\Support\IntegrationDatabases;
use PhpSoftBox\Requisites\Tests\Support\StepMigrator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Throwable;

use function is_array;
use function is_string;
use function json_decode;

use const JSON_THROW_ON_ERROR;

#[CoversClass(BackfillMigrationRunner::class)]
final class BackfillMigrationRunnerIntegrationTest extends TestCase
{
    /**
     * Проверяет: dry-run режим в SQLite не модифицирует данные и возвращает корректный отчет.
     */
    #[Test]
    public function sqliteDryRunAndWriteModeWork(): void
    {
        try {
            $database = IntegrationDatabases::sqliteDatabase();
        } catch (Throwable $exception) {
            self::markTestSkipped($exception->getMessage());
        }

        self::runScenario($database, driver: 'sqlite');
    }

    /**
     * Проверяет: dry-run режим в MariaDB не модифицирует данные и возвращает корректный отчет.
     */
    #[Test]
    public function mariadbDryRunAndWriteModeWork(): void
    {
        try {
            $database = IntegrationDatabases::mariadbDatabase();
        } catch (Throwable $exception) {
            self::markTestSkipped($exception->getMessage());
        }

        self::runScenario($database, driver: 'mariadb');
    }

    /**
     * Проверяет: dry-run режим в Postgres не модифицирует данные и возвращает корректный отчет.
     */
    #[Test]
    public function postgresDryRunAndWriteModeWork(): void
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

        $adapter = new DefaultStorageAdapter($database->manager());
        $runner  = new BackfillMigrationRunner(
            connections: $database->manager(),
            engine: new PayloadMigrationEngine(new PayloadMigrationRegistry([
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
            $adapter->save(new RequisitesRecord(
                profile: 'company',
                selector: 'country:RU',
                schemaVersion: 1,
                subjectType: 'company',
                subjectId: 1001,
                payload: ['inn' => '1111111111'],
                attachments: [],
            ));
            $adapter->save(new RequisitesRecord(
                profile: 'company',
                selector: 'country:RU',
                schemaVersion: 2,
                subjectType: 'company',
                subjectId: 1002,
                payload: ['inn' => '2222222222'],
                attachments: [],
            ));
            $adapter->save(new RequisitesRecord(
                profile: 'company',
                selector: 'country:KZ',
                schemaVersion: 1,
                subjectType: 'company',
                subjectId: 1003,
                payload: ['inn' => '3333333333'],
                attachments: [],
            ));

            $dryReport = $runner->run(profile: 'company', dryRun: true, batchSize: 1);
            self::assertSame(3, $dryReport->processed);
            self::assertSame(2, $dryReport->migrated);
            self::assertSame(1, $dryReport->skipped);
            self::assertSame(0, $dryReport->failed);

            self::assertSame(1, self::schemaVersionBySubject($database, 1001));
            self::assertSame(2, self::schemaVersionBySubject($database, 1002));
            self::assertSame(1, self::schemaVersionBySubject($database, 1003));

            $writeReport = $runner->run(profile: 'company', dryRun: false, batchSize: 2);
            self::assertSame(3, $writeReport->processed);
            self::assertSame(2, $writeReport->migrated);
            self::assertSame(1, $writeReport->skipped);
            self::assertSame(0, $writeReport->failed);

            self::assertSame(3, self::schemaVersionBySubject($database, 1001));
            self::assertSame(3, self::schemaVersionBySubject($database, 1002));
            self::assertSame(1, self::schemaVersionBySubject($database, 1003));

            self::assertSame(['a', 'b'], self::payloadStepsBySubject($database, 1001));
            self::assertSame(['b'], self::payloadStepsBySubject($database, 1002));
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

    private static function schemaVersionBySubject(Database $database, int $subjectId): int
    {
        $row = $database->fetchOne(
            'SELECT schema_version FROM requisites_records WHERE subject_type = :subject_type AND subject_id = :subject_id AND profile = :profile LIMIT 1',
            [
                'subject_type' => 'company',
                'subject_id'   => (string) $subjectId,
                'profile'      => 'company',
            ],
        );

        self::assertTrue(is_array($row));

        return (int) ($row['schema_version'] ?? 0);
    }

    /**
     * @return list<string>
     */
    private static function payloadStepsBySubject(Database $database, int $subjectId): array
    {
        $row = $database->fetchOne(
            'SELECT payload_json FROM requisites_records WHERE subject_type = :subject_type AND subject_id = :subject_id AND profile = :profile LIMIT 1',
            [
                'subject_type' => 'company',
                'subject_id'   => (string) $subjectId,
                'profile'      => 'company',
            ],
        );
        self::assertTrue(is_array($row));

        $raw = $row['payload_json'] ?? null;
        if (!is_string($raw) || $raw === '') {
            return [];
        }

        try {
            $payload = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return [];
        }

        if (!is_array($payload)) {
            return [];
        }

        $steps = $payload['steps'] ?? [];
        if (!is_array($steps)) {
            return [];
        }

        return $steps;
    }
}
