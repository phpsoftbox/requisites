<?php

declare(strict_types=1);

namespace PhpSoftBox\Requisites\Storage;

use DateTimeImmutable;
use DateTimeZone;
use JsonException;
use PhpSoftBox\Database\Connection\ConnectionManagerInterface;
use PhpSoftBox\Database\Exception\QueryException;
use PhpSoftBox\Requisites\Contract\StorageAdapterInterface;
use PhpSoftBox\Requisites\DTO\RequisitesRecord;
use PhpSoftBox\Requisites\DTO\RequisitesSubject;
use PhpSoftBox\Requisites\Exception\StorageException;

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

final readonly class DefaultStorageAdapter implements StorageAdapterInterface
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

            if ($updated === 0) {
                throw new StorageException(sprintf('Requisites record not found by id: %s', (string) $record->id));
            }

            return;
        }

        try {
            $connection
                ->query()
                ->insert($table, $data + ['created_datetime' => $now])
                ->execute();

            return;
        } catch (QueryException) {
            // Конфликт уникальности subject+profile: запись уже есть, обновляем существующую.
        }

        $connection
            ->query()
            ->update($table, $data)
            ->where('subject_type = :subject_type', ['subject_type' => $record->subjectType])
            ->where('subject_id = :subject_id', ['subject_id' => (string) $record->subjectId])
            ->where('profile = :profile', ['profile' => $record->profile])
            ->execute();
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
