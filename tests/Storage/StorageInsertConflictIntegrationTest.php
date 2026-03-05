<?php

declare(strict_types=1);

namespace PhpSoftBox\Requisites\Tests\Storage;

use PhpSoftBox\Database\Database;
use PhpSoftBox\Database\Exception\QueryException;
use PhpSoftBox\Database\SchemaBuilder\TableBlueprint;
use PhpSoftBox\Requisites\DTO\RequisitesRecord;
use PhpSoftBox\Requisites\DTO\RequisitesSubject;
use PhpSoftBox\Requisites\Storage\DefaultStorageAdapter;
use PhpSoftBox\Requisites\Storage\UniqueConstraintViolationDetector;
use PhpSoftBox\Requisites\Tests\Support\IntegrationDatabases;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Throwable;

#[CoversClass(UniqueConstraintViolationDetector::class)]
final class StorageInsertConflictIntegrationTest extends TestCase
{
    #[Test]
    public function sqliteDoesNotMaskNonUniqueInsertFailure(): void
    {
        try {
            $database = IntegrationDatabases::sqliteDatabase();
        } catch (Throwable $exception) {
            self::markTestSkipped($exception->getMessage());
        }

        self::runNonUniqueFailureScenario($database, 'sqlite');
    }

    #[Test]
    public function mariadbDoesNotMaskNonUniqueInsertFailure(): void
    {
        try {
            $database = IntegrationDatabases::mariadbDatabase();
        } catch (Throwable $exception) {
            self::markTestSkipped($exception->getMessage());
        }

        self::runNonUniqueFailureScenario($database, 'mariadb');
    }

    #[Test]
    public function postgresDoesNotMaskNonUniqueInsertFailure(): void
    {
        try {
            $database = IntegrationDatabases::postgresDatabase();
        } catch (Throwable $exception) {
            self::markTestSkipped($exception->getMessage());
        }

        self::runNonUniqueFailureScenario($database, 'postgres');
    }

    #[Test]
    public function postgresConflictRecoveryWorksInsideOuterTransaction(): void
    {
        try {
            $database = IntegrationDatabases::postgresDatabase();
        } catch (Throwable $exception) {
            self::markTestSkipped($exception->getMessage());
        }

        self::prepareRegularSchema($database, 'postgres');
        $adapter = new DefaultStorageAdapter($database->manager(), table: 'requisites_conflict_test');

        try {
            $adapter->save(self::record(['value' => 'before']));

            $database->transaction(function () use ($adapter, $database): void {
                $adapter->save(self::record(['value' => 'after']));
                self::assertSame(['value' => 'after'], $adapter->find(
                    new RequisitesSubject('company', 1),
                    'company',
                )?->payload);
                self::assertNotNull($database->fetchOne('SELECT 1'));
            });
        } finally {
            $database->execute('DROP TABLE IF EXISTS requisites_conflict_test');
        }
    }

    /** @param 'sqlite'|'mariadb'|'postgres' $driver */
    private static function runNonUniqueFailureScenario(Database $database, string $driver): void
    {
        $database->execute('DROP TABLE IF EXISTS requisites_conflict_test');
        $database->connection()->schema()->create(
            'requisites_conflict_test',
            static function (TableBlueprint $table) use ($driver): void {
                if ($driver === 'mariadb') {
                    $table->engine('InnoDB');
                }

                $table->id();
                $table->string('subject_type', 120);
                $table->string('subject_id', 64);
                $table->string('profile', 80);
                $table->string('selector', 120);
                $table->integer('schema_version');
                $table->json('payload_json')->nullable();
                $table->json('attachments_json')->nullable();
                $table->datetime('created_datetime');
                $table->datetime('updated_datetime');
                $table->string('required_marker', 20);
                $table->unique(['subject_type', 'subject_id', 'profile'], 'requisites_conflict_unique');
            },
        );

        try {
            $database->execute(
                'INSERT INTO requisites_conflict_test '
                . '(subject_type, subject_id, profile, selector, schema_version, payload_json, attachments_json, created_datetime, updated_datetime, required_marker) '
                . 'VALUES (:type, :id, :profile, :selector, :version, :payload, :attachments, :created, :updated, :marker)',
                [
                    'type'        => 'company',
                    'id'          => '1',
                    'profile'     => 'company',
                    'selector'    => 'default',
                    'version'     => 1,
                    'payload'     => '{"value":"before"}',
                    'attachments' => '{}',
                    'created'     => '2026-08-27 00:00:00',
                    'updated'     => '2026-08-27 00:00:00',
                    'marker'      => 'required',
                ],
            );

            $adapter = new DefaultStorageAdapter($database->manager(), table: 'requisites_conflict_test');

            try {
                $adapter->save(self::record(['value' => 'must-not-be-written']));
                self::fail('A non-unique INSERT error was incorrectly recovered as an UPDATE.');
            } catch (QueryException) {
            }

            $row = $database->fetchOne('SELECT payload_json FROM requisites_conflict_test WHERE id = 1');
            self::assertIsArray($row);
            self::assertStringContainsString('before', (string) ($row['payload_json'] ?? ''));
        } finally {
            $database->execute('DROP TABLE IF EXISTS requisites_conflict_test');
        }
    }

    /** @param 'sqlite'|'mariadb'|'postgres' $driver */
    private static function prepareRegularSchema(Database $database, string $driver): void
    {
        $database->execute('DROP TABLE IF EXISTS requisites_conflict_test');
        $database->connection()->schema()->create(
            'requisites_conflict_test',
            static function (TableBlueprint $table) use ($driver): void {
                if ($driver === 'mariadb') {
                    $table->engine('InnoDB');
                }
                $table->id();
                $table->string('subject_type', 120);
                $table->string('subject_id', 64);
                $table->string('profile', 80);
                $table->string('selector', 120);
                $table->integer('schema_version');
                $table->json('payload_json')->nullable();
                $table->json('attachments_json')->nullable();
                $table->datetime('created_datetime');
                $table->datetime('updated_datetime');
                $table->unique(['subject_type', 'subject_id', 'profile'], 'requisites_conflict_unique');
            },
        );
    }

    /** @param array<string, mixed> $payload */
    private static function record(array $payload): RequisitesRecord
    {
        return new RequisitesRecord(
            profile: 'company',
            selector: 'default',
            schemaVersion: 1,
            subjectType: 'company',
            subjectId: 1,
            payload: $payload,
        );
    }
}
