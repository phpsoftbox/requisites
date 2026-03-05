<?php

declare(strict_types=1);

namespace PhpSoftBox\Requisites\Migration;

use InvalidArgumentException;
use PhpSoftBox\Database\Connection\ConnectionManagerInterface;
use PhpSoftBox\Requisites\Contract\MigratableStorageInterface;
use PhpSoftBox\Requisites\Storage\DefaultStorageAdapter;
use Throwable;

use function sprintf;

final readonly class BackfillMigrationRunner
{
    private MigratableStorageInterface $storage;

    /**
     * ConnectionManagerInterface remains accepted for backward compatibility with the original runner.
     */
    public function __construct(
        ConnectionManagerInterface|MigratableStorageInterface $connections,
        private PayloadMigrationEngine $engine,
        private MigrationTargetVersionResolverInterface $targetResolver,
        string $connectionName = 'default',
        string $table = 'requisites_records',
    ) {
        $this->storage = $connections instanceof MigratableStorageInterface
            ? $connections
            : new DefaultStorageAdapter($connections, $connectionName, $table);
    }

    public function run(
        string $profile,
        ?string $selector = null,
        ?int $fromVersion = null,
        ?int $toVersion = null,
        bool $dryRun = false,
        int $batchSize = 100,
    ): BackfillMigrationReport {
        $this->validateOptions($profile, $fromVersion, $toVersion, $batchSize);

        $info      = $this->storage->migrationStorageInfo($profile);
        $processed = 0;
        $migrated  = 0;
        $skipped   = 0;
        $failed    = 0;
        $errors    = [];
        $lastId    = null;

        while (true) {
            $records = $this->storage->migrationBatch($profile, $selector, $lastId, $batchSize);
            if ($records === []) {
                break;
            }

            foreach ($records as $record) {
                ++$processed;
                if ($record->id === null) {
                    ++$failed;
                    $errors[] = 'Persisted migration record has no id.';
                    continue;
                }

                $lastId = $record->id;

                try {
                    if ($fromVersion !== null && $record->schemaVersion < $fromVersion) {
                        ++$skipped;
                        continue;
                    }

                    $targetVersion = $toVersion
                        ?? $this->targetResolver->targetVersion($record->profile, $record->selector);
                    if ($record->schemaVersion >= $targetVersion) {
                        ++$skipped;
                        continue;
                    }

                    $migratedRecord = $this->engine->migrate($record, $targetVersion);
                    if (!$dryRun) {
                        $this->storage->saveMigrated($migratedRecord, $record->schemaVersion);
                    }

                    ++$migrated;
                } catch (Throwable $exception) {
                    ++$failed;
                    $errors[] = sprintf(
                        'Record id=%s failed: %s',
                        (string) $record->id,
                        $exception->getMessage(),
                    );
                }
            }

            if ($lastId === null) {
                break;
            }
        }

        return new BackfillMigrationReport(
            processed: $processed,
            migrated: $migrated,
            skipped: $skipped,
            failed: $failed,
            errors: $errors,
            profile: $profile,
            storageDriver: $info->driver,
            connection: $info->connection,
            table: $info->table,
        );
    }

    private function validateOptions(
        string $profile,
        ?int $fromVersion,
        ?int $toVersion,
        int $batchSize,
    ): void {
        if ($profile === '') {
            throw new InvalidArgumentException('Profile must not be empty.');
        }
        if ($batchSize < 1) {
            throw new InvalidArgumentException('Batch size must be greater than or equal to 1.');
        }
        if ($fromVersion !== null && $fromVersion < 1) {
            throw new InvalidArgumentException('From version must be greater than or equal to 1.');
        }
        if ($toVersion !== null && $toVersion < 1) {
            throw new InvalidArgumentException('To version must be greater than or equal to 1.');
        }
        if ($fromVersion !== null && $toVersion !== null && $fromVersion > $toVersion) {
            throw new InvalidArgumentException('From version must be less than or equal to target version.');
        }
    }
}
