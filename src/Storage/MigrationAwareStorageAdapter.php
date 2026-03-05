<?php

declare(strict_types=1);

namespace PhpSoftBox\Requisites\Storage;

use PhpSoftBox\Requisites\Contract\AtomicCreateStorageInterface;
use PhpSoftBox\Requisites\Contract\MigratableStorageInterface;
use PhpSoftBox\Requisites\Contract\StorageAdapterInterface;
use PhpSoftBox\Requisites\DTO\RequisitesInsertResult;
use PhpSoftBox\Requisites\DTO\RequisitesRecord;
use PhpSoftBox\Requisites\DTO\RequisitesSubject;
use PhpSoftBox\Requisites\Exception\StorageException;
use PhpSoftBox\Requisites\Migration\MigrationStorageInfo;
use PhpSoftBox\Requisites\Migration\MigrationTargetVersionResolverInterface;
use PhpSoftBox\Requisites\Migration\PayloadMigrationEngine;

final readonly class MigrationAwareStorageAdapter implements MigratableStorageInterface
{
    public function __construct(
        private StorageAdapterInterface $inner,
        private PayloadMigrationEngine $migrationEngine,
        private MigrationTargetVersionResolverInterface $targetResolver,
    ) {
    }

    public function find(RequisitesSubject $subject, string $profile): ?RequisitesRecord
    {
        $record = $this->inner->find($subject, $profile);
        if ($record === null) {
            return null;
        }

        $migrated = $this->migrateRecordIfNeeded($record);
        if ($migrated->schemaVersion !== $record->schemaVersion && $migrated->id !== null) {
            $this->inner->save($migrated);
        }

        return $migrated;
    }

    public function create(RequisitesSubject $subject, string $profile): RequisitesRecord
    {
        return $this->inner->create($subject, $profile);
    }

    public function save(RequisitesRecord $record): void
    {
        $this->inner->save($this->migrateRecordIfNeeded($record));
    }

    public function insertOrFind(RequisitesRecord $record): RequisitesInsertResult
    {
        if (!$this->inner instanceof AtomicCreateStorageInterface) {
            throw new StorageException('Wrapped storage adapter does not support atomic create.');
        }

        return $this->inner->insertOrFind($record);
    }

    public function migrationBatch(
        string $profile,
        ?string $selector,
        int|string|null $afterId,
        int $limit,
    ): array {
        return $this->migratableInner()->migrationBatch($profile, $selector, $afterId, $limit);
    }

    public function saveMigrated(RequisitesRecord $record, int $previousVersion): void
    {
        $this->migratableInner()->saveMigrated($record, $previousVersion);
    }

    public function migrationStorageInfo(string $profile): MigrationStorageInfo
    {
        return $this->migratableInner()->migrationStorageInfo($profile);
    }

    private function migratableInner(): MigratableStorageInterface
    {
        if (!$this->inner instanceof MigratableStorageInterface) {
            throw new StorageException('Wrapped storage adapter does not support backfill migrations.');
        }

        return $this->inner;
    }

    private function migrateRecordIfNeeded(RequisitesRecord $record): RequisitesRecord
    {
        $targetVersion = $this->targetResolver->targetVersion($record->profile, $record->selector);
        if ($record->schemaVersion >= $targetVersion) {
            return $record;
        }

        return $this->migrationEngine->migrate($record, $targetVersion);
    }
}
