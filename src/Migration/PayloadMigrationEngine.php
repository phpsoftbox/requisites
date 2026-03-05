<?php

declare(strict_types=1);

namespace PhpSoftBox\Requisites\Migration;

use InvalidArgumentException;
use PhpSoftBox\Requisites\Contract\PayloadMigrationRegistryInterface;
use PhpSoftBox\Requisites\DTO\RequisitesRecord;

final readonly class PayloadMigrationEngine
{
    public function __construct(
        private PayloadMigrationRegistryInterface $registry,
    ) {
    }

    public function migrate(RequisitesRecord $record, int $targetVersion): RequisitesRecord
    {
        if ($targetVersion < 1) {
            throw new InvalidArgumentException('Target version must be greater than or equal to 1.');
        }

        if ($record->schemaVersion < 1) {
            throw new InvalidArgumentException('Record schema version must be greater than or equal to 1.');
        }

        if ($record->schemaVersion >= $targetVersion) {
            return $record;
        }

        $payload     = $record->payload;
        $stepVersion = $record->schemaVersion;
        foreach ($this->registry->chain($record->profile, $record->selector, $record->schemaVersion, $targetVersion) as $migrator) {
            $nextStep = $stepVersion + 1;
            $payload  = $migrator->migrate($payload, new RequisitesMigrationContext(
                profile: $record->profile,
                selector: $record->selector,
                fromVersion: $stepVersion,
                toVersion: $nextStep,
                recordId: $record->id,
            ));
            $stepVersion = $nextStep;
        }

        return new RequisitesRecord(
            profile: $record->profile,
            selector: $record->selector,
            schemaVersion: $targetVersion,
            subjectType: $record->subjectType,
            subjectId: $record->subjectId,
            payload: $payload,
            attachments: $record->attachments,
            id: $record->id,
        );
    }
}
