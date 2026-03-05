<?php

declare(strict_types=1);

namespace PhpSoftBox\Requisites\Storage;

use DateTimeImmutable;
use DateTimeZone;
use JsonException;
use PhpSoftBox\Database\Connection\ConnectionManagerInterface;
use PhpSoftBox\Database\Contracts\ConnectionInterface;
use PhpSoftBox\Database\Exception\QueryException;
use PhpSoftBox\Requisites\Contract\MigratableStorageInterface;
use PhpSoftBox\Requisites\DTO\RequisitesInsertResult;
use PhpSoftBox\Requisites\DTO\RequisitesRecord;
use PhpSoftBox\Requisites\DTO\RequisitesSubject;
use PhpSoftBox\Requisites\Exception\StorageException;
use PhpSoftBox\Requisites\Migration\MigrationStorageInfo;

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

final readonly class DefaultStorageAdapter implements MigratableStorageInterface
{
    public function __construct(
        private ConnectionManagerInterface $connections,
        private string $connectionName = 'default',
        private string $table = 'requisites_records',
    ) {
    }

    public function find(RequisitesSubject $subject, string $profile): ?RequisitesRecord
    {
        $connection = $this->connections->read($this->connectionName);
        $table      = $connection->table($this->table);

        $row = $connection
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
            ->where('subject_type = :subject_type', ['subject_type' => $subject->type])
            ->where('subject_id = :subject_id', ['subject_id' => (string) $subject->id])
            ->where('profile = :profile', ['profile' => $profile])
            ->limit(1)
            ->fetchOne();

        if (!is_array($row)) {
            return null;
        }

        return $this->hydrateRecord($row);
    }

    public function create(RequisitesSubject $subject, string $profile): RequisitesRecord
    {
        return new RequisitesRecord(
            profile: $profile,
            selector: 'default',
            schemaVersion: 1,
            subjectType: $subject->type,
            subjectId: $subject->id,
            payload: [],
            attachments: [],
            id: null,
        );
    }

    public function save(RequisitesRecord $record): void
    {
        $connection = $this->connections->write($this->connectionName);
        $table      = $connection->table($this->table);
        $now        = $this->now();

        $data = [
            'profile'          => $record->profile,
            'selector'         => $record->selector,
            'schema_version'   => $record->schemaVersion,
            'subject_type'     => $record->subjectType,
            'subject_id'       => (string) $record->subjectId,
            'payload_json'     => $this->encodeJson($record->payload),
            'attachments_json' => $this->encodeJson($record->attachments),
            'updated_datetime' => $now,
        ];

        if ($record->id !== null) {
            $updated = $connection
                ->query()
                ->update($table, $data)
                ->where('id = :id', ['id' => $record->id])
                ->execute();

            if ($updated === 0 && !$this->existsById($connection, $table, $record->id)) {
                throw new StorageException(sprintf('Requisites record not found by id: %s', (string) $record->id));
            }

            return;
        }

        $insertException = null;
        try {
            $connection->transaction(static function (ConnectionInterface $transaction) use ($table, $data, $now): void {
                $transaction
                    ->query()
                    ->insert($table, $data + ['created_datetime' => $now])
                    ->execute();
            });

            return;
        } catch (QueryException $exception) {
            $insertException = $exception;
        }

        if (!new UniqueConstraintViolationDetector()->matches($insertException)) {
            throw $insertException;
        }

        if (!$this->existsBySubjectAndProfile($connection, $table, $record)) {
            throw $insertException;
        }

        $updated = $connection
            ->query()
            ->update($table, $data)
            ->where('subject_type = :subject_type', ['subject_type' => $record->subjectType])
            ->where('subject_id = :subject_id', ['subject_id' => (string) $record->subjectId])
            ->where('profile = :profile', ['profile' => $record->profile])
            ->execute();

        if ($updated === 0 && !$this->existsBySubjectAndProfile($connection, $table, $record)) {
            throw new StorageException('Requisites record disappeared during INSERT conflict recovery.', 0, $insertException);
        }
    }

    public function insertOrFind(RequisitesRecord $record): RequisitesInsertResult
    {
        if ($record->id !== null) {
            throw new StorageException('insertOrFind() accepts only transient requisites records.');
        }

        $connection = $this->connections->write($this->connectionName);
        $table      = $connection->table($this->table);
        $now        = $this->now();
        $data       = [
            'profile'          => $record->profile,
            'selector'         => $record->selector,
            'schema_version'   => $record->schemaVersion,
            'subject_type'     => $record->subjectType,
            'subject_id'       => (string) $record->subjectId,
            'payload_json'     => $this->encodeJson($record->payload),
            'attachments_json' => $this->encodeJson($record->attachments),
            'updated_datetime' => $now,
            'created_datetime' => $now,
        ];

        $inserted = true;
        try {
            $connection->transaction(static function (ConnectionInterface $transaction) use ($table, $data): void {
                $transaction->query()->insert($table, $data)->execute();
            });
        } catch (QueryException $exception) {
            if (!new UniqueConstraintViolationDetector()->matches($exception)) {
                throw $exception;
            }
            $inserted = false;
        }

        $canonical = $this->findBySubjectAndProfile($connection, $table, $record);
        if ($canonical === null) {
            throw new StorageException('Requisites record was not found after atomic create.');
        }

        return new RequisitesInsertResult($canonical, $inserted);
    }

    public function migrationBatch(
        string $profile,
        ?string $selector,
        int|string|null $afterId,
        int $limit,
    ): array {
        $connection = $this->connections->write($this->connectionName);
        $table      = $connection->table($this->table);
        $query      = $connection
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
            ->orderBy('id', 'ASC')
            ->limit($limit);

        if ($afterId !== null) {
            $query->where('id > :after_id', ['after_id' => $afterId]);
        }
        if (is_string($selector) && $selector !== '') {
            $query->where('selector = :selector', ['selector' => $selector]);
        }

        $records = [];
        foreach ($query->fetchAll() as $row) {
            if (is_array($row)) {
                $records[] = $this->hydrateRecord($row);
            }
        }

        return $records;
    }

    public function saveMigrated(RequisitesRecord $record, int $previousVersion): void
    {
        if ($record->id === null) {
            throw new StorageException('Backfill migration requires a persisted record id.');
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
            throw new StorageException(sprintf(
                'Backfill update conflict for record id=%s (expected version=%d).',
                (string) $record->id,
                $previousVersion,
            ));
        }
    }

    public function migrationStorageInfo(string $profile): MigrationStorageInfo
    {
        return new MigrationStorageInfo('default', $this->connectionName, $this->table);
    }

    private function existsById(ConnectionInterface $connection, string $table, int|string $id): bool
    {
        return is_array($connection
            ->query()
            ->select(['id'])
            ->from($table)
            ->where('id = :id', ['id' => $id])
            ->limit(1)
            ->fetchOne());
    }

    private function existsBySubjectAndProfile(
        ConnectionInterface $connection,
        string $table,
        RequisitesRecord $record,
    ): bool {
        return $this->findBySubjectAndProfile($connection, $table, $record) !== null;
    }

    private function findBySubjectAndProfile(
        ConnectionInterface $connection,
        string $table,
        RequisitesRecord $record,
    ): ?RequisitesRecord {
        $row = $connection
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
            ->where('subject_type = :subject_type', ['subject_type' => $record->subjectType])
            ->where('subject_id = :subject_id', ['subject_id' => (string) $record->subjectId])
            ->where('profile = :profile', ['profile' => $record->profile])
            ->limit(1)
            ->fetchOne();

        return is_array($row) ? $this->hydrateRecord($row) : null;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrateRecord(array $row): RequisitesRecord
    {
        return new RequisitesRecord(
            profile: (string) ($row['profile'] ?? ''),
            selector: (string) ($row['selector'] ?? 'default'),
            schemaVersion: (int) ($row['schema_version'] ?? 1),
            subjectType: (string) ($row['subject_type'] ?? ''),
            subjectId: $this->normalizeId($row['subject_id'] ?? ''),
            payload: $this->decodeJsonObject($row, 'payload_json'),
            attachments: $this->decodeJsonObject($row, 'attachments_json'),
            id: $this->normalizeNullableId($row['id'] ?? null),
        );
    }

    /**
     * @param array<string, mixed> $data
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
            throw new StorageException(sprintf('Invalid JSON in "%s".', $key), 0, $exception);
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
            throw new StorageException('Failed to encode requisites payload to JSON.', 0, $exception);
        }
    }

    private function normalizeNullableId(mixed $value): int|string|null
    {
        if ($value === null || $value === '') {
            return null;
        }

        return $this->normalizeId($value);
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
