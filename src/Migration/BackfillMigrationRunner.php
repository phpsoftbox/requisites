<?php

declare(strict_types=1);

namespace PhpSoftBox\Requisites\Migration;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use JsonException;
use PhpSoftBox\Database\Connection\ConnectionManagerInterface;
use PhpSoftBox\Requisites\DTO\RequisitesRecord;
use RuntimeException;
use Throwable;

use function array_key_exists;
use function is_array;
use function is_int;
use function is_string;
use function json_decode;
use function json_encode;
use function preg_match;
use function sprintf;

use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

final readonly class BackfillMigrationRunner
{
    public function __construct(
        private ConnectionManagerInterface $connections,
        private PayloadMigrationEngine $engine,
        private MigrationTargetVersionResolverInterface $targetResolver,
        private string $connectionName = 'default',
        private string $table = 'requisites_records',
    ) {
    }

    public function run(
        string $profile,
        ?string $selector = null,
        ?int $fromVersion = null,
        ?int $toVersion = null,
        bool $dryRun = false,
        int $batchSize = 100,
    ): BackfillMigrationReport {
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

        $connection = $this->connections->write($this->connectionName);
        $table      = $connection->table($this->table);

        $processed = 0;
        $migrated  = 0;
        $skipped   = 0;
        $failed    = 0;
        $errors    = [];

        $lastId = 0;
        while (true) {
            $query = $connection
                ->query()
                ->select([
                    'id',
                    'profile',
                    'selector',
                    'schema_version',
                    'subject_type',
                    'subject_id',
                    'payload_json',
                    'attachments_json',
                ])
                ->from($table)
                ->where('profile = :profile', ['profile' => $profile])
                ->where('id > :last_id', ['last_id' => $lastId])
                ->orderBy('id', 'ASC')
                ->limit($batchSize);

            if (is_string($selector) && $selector !== '') {
                $query->where('selector = :selector', ['selector' => $selector]);
            }

            $rows = $query->fetchAll();
            if ($rows === []) {
                break;
            }

            foreach ($rows as $row) {
                $processed++;

                if (!is_array($row)) {
                    $failed++;
                    $errors[] = 'Unexpected row format received from storage.';
                    continue;
                }

                $id = $this->normalizeIntId($row['id'] ?? null);
                if ($id <= 0) {
                    $failed++;
                    $errors[] = 'Invalid row id received from storage.';
                    continue;
                }

                $lastId = $id;

                try {
                    $record = $this->hydrateRecord($row);
                    if ($fromVersion !== null && $record->schemaVersion < $fromVersion) {
                        $skipped++;
                        continue;
                    }

                    $targetVersion = $toVersion ?? $this->targetResolver->targetVersion($record->profile, $record->selector);
                    if ($record->schemaVersion >= $targetVersion) {
                        $skipped++;
                        continue;
                    }

                    $migratedRecord = $this->engine->migrate($record, $targetVersion);
                    if (!$dryRun) {
                        $this->persistMigrated($migratedRecord, $record->schemaVersion);
                    }

                    $migrated++;
                } catch (Throwable $exception) {
                    $failed++;
                    $errors[] = sprintf(
                        'Record id=%d failed: %s',
                        $id,
                        $exception->getMessage(),
                    );
                }
            }
        }

        return new BackfillMigrationReport(
            processed: $processed,
            migrated: $migrated,
            skipped: $skipped,
            failed: $failed,
            errors: $errors,
        );
    }

    private function persistMigrated(RequisitesRecord $record, int $previousVersion): void
    {
        if (!is_int($record->id)) {
            throw new RuntimeException('Backfill migration requires integer record id.');
        }

        $connection = $this->connections->write($this->connectionName);
        $table      = $connection->table($this->table);
        $updated    = $connection
            ->query()
            ->update($table, [
                'payload_json'     => $this->encodeJson($record->payload),
                'attachments_json' => $this->encodeJson($record->attachments),
                'schema_version'   => $record->schemaVersion,
                'updated_datetime' => $this->now(),
            ])
            ->where('id = :id', ['id' => $record->id])
            ->where('schema_version = :schema_version', ['schema_version' => $previousVersion])
            ->execute();

        if ($updated !== 1) {
            throw new RuntimeException(sprintf(
                'Backfill update conflict for record id=%d (expected version=%d).',
                $record->id,
                $previousVersion,
            ));
        }
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrateRecord(array $row): RequisitesRecord
    {
        $id = $this->normalizeIntId($row['id'] ?? null);
        if ($id <= 0) {
            throw new RuntimeException('Invalid record id.');
        }

        return new RequisitesRecord(
            profile: (string) ($row['profile'] ?? ''),
            selector: (string) ($row['selector'] ?? 'default'),
            schemaVersion: (int) ($row['schema_version'] ?? 1),
            subjectType: (string) ($row['subject_type'] ?? ''),
            subjectId: $this->normalizeId($row['subject_id'] ?? ''),
            payload: $this->decodeJsonObject($row, 'payload_json'),
            attachments: $this->decodeJsonObject($row, 'attachments_json'),
            id: $id,
        );
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function decodeJsonObject(array $data, string $key): array
    {
        if (!array_key_exists($key, $data) || $data[$key] === null || $data[$key] === '') {
            return [];
        }

        $raw = $data[$key];
        if (!is_string($raw)) {
            return [];
        }

        try {
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException(sprintf('Invalid JSON in "%s".', $key), 0, $exception);
        }

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param array<string, mixed> $value
     */
    private function encodeJson(array $value): string
    {
        try {
            return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } catch (JsonException $exception) {
            throw new RuntimeException('Failed to encode requisites payload to JSON.', 0, $exception);
        }
    }

    private function normalizeIntId(mixed $value): int
    {
        if (is_int($value)) {
            return $value;
        }
        if (is_string($value) && preg_match('/^\d+$/', $value) === 1) {
            return (int) $value;
        }

        return 0;
    }

    private function normalizeId(mixed $value): int|string
    {
        if (is_int($value)) {
            return $value;
        }
        if (is_string($value) && preg_match('/^-?\d+$/', $value) === 1) {
            return (int) $value;
        }

        return (string) $value;
    }

    private function now(): string
    {
        return new DateTimeImmutable('now', new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }
}
