<?php

declare(strict_types=1);

namespace PhpSoftBox\Requisites\Storage;

use PhpSoftBox\Requisites\Contract\StorageAdapterInterface;
use PhpSoftBox\Requisites\DTO\RequisitesRecord;
use PhpSoftBox\Requisites\DTO\RequisitesSubject;
use PhpSoftBox\Requisites\Migration\MigrationTargetVersionResolverInterface;
use PhpSoftBox\Requisites\Migration\PayloadMigrationEngine;

final readonly class MigrationAwareStorageAdapter implements StorageAdapterInterface
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
        $record  = $this->inner->create($subject, $profile);
        $target  = $this->targetResolver->targetVersion($record->profile, $record->selector);
        $version = $record->schemaVersion >= $target ? $record->schemaVersion : $target;
        if ($version === $record->schemaVersion) {
            return $record;
        }

        return new RequisitesRecord(
            profile: $record->profile,
            selector: $record->selector,
            schemaVersion: $version,
            subjectType: $record->subjectType,
            subjectId: $record->subjectId,
            payload: $record->payload,
            attachments: $record->attachments,
            id: $record->id,
        );
    }

    public function save(RequisitesRecord $record): void
    {
        $this->inner->save($this->migrateRecordIfNeeded($record));
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
