<?php

declare(strict_types=1);

namespace PhpSoftBox\Requisites\Storage;

use DateTimeImmutable;
use DateTimeZone;
use JsonException;
use PhpSoftBox\Database\Connection\ConnectionManagerInterface;
use PhpSoftBox\Database\Contracts\ConnectionInterface;
use PhpSoftBox\Database\Exception\QueryException;
use PhpSoftBox\DataCasting\DefaultTypeCasterFactory;
use PhpSoftBox\DataCasting\Options\TypeCastOptionsManager;
use PhpSoftBox\Orm\Metadata\AttributeMetadataProvider;
use PhpSoftBox\Orm\Metadata\ClassMetadata;
use PhpSoftBox\Orm\Repository\AutoEntityMapper;
use PhpSoftBox\Requisites\Contract\StorageAdapterInterface;
use PhpSoftBox\Requisites\DTO\RequisitesRecord;
use PhpSoftBox\Requisites\DTO\RequisitesSubject;
use PhpSoftBox\Requisites\Exception\StorageException;
use ReflectionClass;
use Throwable;

use function array_key_exists;
use function is_array;
use function is_int;
use function is_string;
use function json_decode;
use function json_encode;
use function preg_match;
use function property_exists;
use function sprintf;

use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

final class OrmEntityStorageAdapter implements StorageAdapterInterface
{
    private readonly AttributeMetadataProvider $metadataProvider;
    private readonly AutoEntityMapper $mapper;
    private readonly OrmEntityFieldMap $fieldMap;
    private readonly ClassMetadata $metadata;

    /**
     * @param class-string $entityClass
     */
    public function __construct(
        private readonly ConnectionManagerInterface $connections,
        private readonly string $entityClass,
        private readonly ?string $connectionName = null,
        ?OrmEntityFieldMap $fieldMap = null,
        ?AttributeMetadataProvider $metadataProvider = null,
        ?AutoEntityMapper $mapper = null,
    ) {
        $this->fieldMap         = $fieldMap ?? new OrmEntityFieldMap();
        $this->metadataProvider = $metadataProvider ?? new AttributeMetadataProvider();
        $this->metadata         = $this->metadataProvider->for($this->entityClass);

        $this->mapper = $mapper ?? $this->createMapper();
    }

    private function createMapper(): AutoEntityMapper
    {
        return new AutoEntityMapper(
            $this->metadataProvider,
            new DefaultTypeCasterFactory()->create(),
            new TypeCastOptionsManager(),
        );
    }

    public function find(RequisitesSubject $subject, string $profile): ?RequisitesRecord
    {
        $connection = $this->readConnection();
        $table      = $connection->table($this->metadata->table);

        $subjectTypeColumn = $this->columnFor($this->fieldMap->subjectTypeProperty);
        $subjectIdColumn   = $this->columnFor($this->fieldMap->subjectIdProperty);
        $profileColumn     = $this->columnFor($this->fieldMap->profileProperty);

        $row = $connection
            ->query()
            ->select(['*'])
            ->from($table)
            ->where($subjectTypeColumn . ' = :subject_type', ['subject_type' => $subject->type])
            ->where($subjectIdColumn . ' = :subject_id', ['subject_id' => (string) $subject->id])
            ->where($profileColumn . ' = :profile', ['profile' => $profile])
            ->limit(1)
            ->fetchOne();

        if (!is_array($row)) {
            return null;
        }

        try {
            $entity = $this->mapper->hydrate($this->entityClass, $row);
        } catch (Throwable $exception) {
            throw new StorageException('Failed to hydrate ORM entity from requisites row.', 0, $exception);
        }

        return $this->toRecord($entity);
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
        );
    }

    public function save(RequisitesRecord $record): void
    {
        $connection = $this->writeConnection();
        $table      = $connection->table($this->metadata->table);

        $dataForUpdate = $this->toDbData($record, includeCreatedAt: false);
        if ($record->id !== null) {
            $updated = $connection
                ->query()
                ->update($table, $dataForUpdate)
                ->where($this->columnFor($this->fieldMap->idProperty) . ' = :id', ['id' => $record->id])
                ->execute();

            if ($updated === 0) {
                throw new StorageException(sprintf('ORM requisites record not found by id: %s', (string) $record->id));
            }

            return;
        }

        try {
            $connection
                ->query()
                ->insert($table, $this->toDbData($record, includeCreatedAt: true))
                ->execute();

            return;
        } catch (QueryException) {
            // Конфликт по уникальности: обновляем существующую строку.
        }

        $connection
            ->query()
            ->update($table, $dataForUpdate)
            ->where($this->columnFor($this->fieldMap->subjectTypeProperty) . ' = :subject_type', ['subject_type' => $record->subjectType])
            ->where($this->columnFor($this->fieldMap->subjectIdProperty) . ' = :subject_id', ['subject_id' => (string) $record->subjectId])
            ->where($this->columnFor($this->fieldMap->profileProperty) . ' = :profile', ['profile' => $record->profile])
            ->execute();
    }

    private function readConnection(): ConnectionInterface
    {
        return $this->connections->read($this->resolvedConnectionName());
    }

    private function writeConnection(): ConnectionInterface
    {
        return $this->connections->write($this->resolvedConnectionName());
    }

    private function resolvedConnectionName(): string
    {
        if (is_string($this->connectionName) && $this->connectionName !== '') {
            return $this->connectionName;
        }

        if (is_string($this->metadata->connection) && $this->metadata->connection !== '') {
            return $this->metadata->connection;
        }

        return 'default';
    }

    private function toRecord(object $entity): RequisitesRecord
    {
        $id             = $this->normalizeNullableId($this->readProperty($entity, $this->fieldMap->idProperty));
        $profile        = (string) $this->readProperty($entity, $this->fieldMap->profileProperty);
        $selectorRaw    = $this->readProperty($entity, $this->fieldMap->selectorProperty);
        $schemaVersion  = (int) $this->readProperty($entity, $this->fieldMap->schemaVersionProperty);
        $subjectType    = (string) $this->readProperty($entity, $this->fieldMap->subjectTypeProperty);
        $subjectIdRaw   = $this->readProperty($entity, $this->fieldMap->subjectIdProperty);
        $payloadRaw     = $this->readProperty($entity, $this->fieldMap->payloadProperty);
        $attachmentsRaw = $this->readProperty($entity, $this->fieldMap->attachmentsProperty);

        return new RequisitesRecord(
            profile: $profile,
            selector: (is_string($selectorRaw) && $selectorRaw !== '') ? $selectorRaw : 'default',
            schemaVersion: $schemaVersion > 0 ? $schemaVersion : 1,
            subjectType: $subjectType,
            subjectId: $this->normalizeId($subjectIdRaw),
            payload: $this->normalizeJsonPayload($payloadRaw, 'payload'),
            attachments: $this->normalizeJsonPayload($attachmentsRaw, 'attachments'),
            id: $id,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function toDbData(RequisitesRecord $record, bool $includeCreatedAt): array
    {
        try {
            $entity = new ReflectionClass($this->entityClass)->newInstanceWithoutConstructor();
        } catch (Throwable $exception) {
            throw new StorageException('Failed to instantiate ORM entity for requisites write.', 0, $exception);
        }

        $this->setProperty($entity, $this->fieldMap->profileProperty, $record->profile);
        $this->setProperty($entity, $this->fieldMap->selectorProperty, $record->selector);
        $this->setProperty($entity, $this->fieldMap->schemaVersionProperty, $record->schemaVersion);
        $this->setProperty($entity, $this->fieldMap->subjectTypeProperty, $record->subjectType);
        $this->setProperty($entity, $this->fieldMap->subjectIdProperty, (string) $record->subjectId);
        $this->setProperty($entity, $this->fieldMap->payloadProperty, $this->valueForJsonProperty($this->fieldMap->payloadProperty, $record->payload));
        $this->setProperty($entity, $this->fieldMap->attachmentsProperty, $this->valueForJsonProperty($this->fieldMap->attachmentsProperty, $record->attachments));

        if ($record->id !== null) {
            $this->setProperty($entity, $this->fieldMap->idProperty, $record->id);
        }

        $now = $this->now();
        if ($this->fieldMap->updatedAtProperty !== null) {
            $this->setProperty($entity, $this->fieldMap->updatedAtProperty, $now);
        }
        if ($includeCreatedAt && $this->fieldMap->createdAtProperty !== null) {
            $this->setProperty($entity, $this->fieldMap->createdAtProperty, $now);
        }

        try {
            $data = $this->mapper->extract($entity);
        } catch (Throwable $exception) {
            throw new StorageException('Failed to extract ORM entity to requisites row.', 0, $exception);
        }

        if ($record->id === null) {
            unset($data[$this->columnFor($this->fieldMap->idProperty)]);
        }
        if (!$includeCreatedAt && $this->fieldMap->createdAtProperty !== null) {
            unset($data[$this->columnFor($this->fieldMap->createdAtProperty)]);
        }

        return $data;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function valueForJsonProperty(string $property, array $payload): mixed
    {
        $columnMeta = $this->metadata->columns[$property] ?? null;
        if ($columnMeta !== null && $columnMeta->type === 'json') {
            return $payload;
        }

        return $this->encodeJson($payload);
    }

    private function readProperty(object $entity, string $property): mixed
    {
        if (!isset($entity->$property) && !property_exists($entity, $property)) {
            throw new StorageException(sprintf('ORM entity property "%s" is not mapped.', $property));
        }

        return $entity->$property;
    }

    private function setProperty(object $entity, string $property, mixed $value): void
    {
        if (!array_key_exists($property, $this->metadata->columns)) {
            throw new StorageException(sprintf('ORM entity property "%s" is not present in metadata columns.', $property));
        }

        $entity->$property = $value;
    }

    private function columnFor(string $property): string
    {
        $meta = $this->metadata->columns[$property] ?? null;
        if ($meta === null) {
            throw new StorageException(sprintf('ORM metadata column for property "%s" is missing.', $property));
        }

        return $meta->column;
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

    /**
     * @return array<string, mixed>
     */
    private function normalizeJsonPayload(mixed $value, string $field): array
    {
        if ($value === null || $value === '') {
            return [];
        }
        if (is_array($value)) {
            return $value;
        }
        if (!is_string($value)) {
            return [];
        }

        try {
            $decoded = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new StorageException(sprintf('Invalid JSON in ORM requisites field "%s".', $field), 0, $exception);
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
            throw new StorageException('Failed to encode requisites JSON payload.', 0, $exception);
        }
    }

    private function now(): string
    {
        return new DateTimeImmutable('now', new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }
}
