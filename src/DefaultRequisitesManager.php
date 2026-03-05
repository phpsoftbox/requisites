<?php

declare(strict_types=1);

namespace PhpSoftBox\Requisites;

use PhpSoftBox\Requisites\Contract\AtomicCreateStorageInterface;
use PhpSoftBox\Requisites\Contract\ManagedRequisitesManagerInterface;
use PhpSoftBox\Requisites\Contract\RequisitesProfileRegistryInterface;
use PhpSoftBox\Requisites\Contract\RequisitesValidatorInterface;
use PhpSoftBox\Requisites\Contract\SchemaProviderInterface;
use PhpSoftBox\Requisites\Contract\SelectorResolverInterface;
use PhpSoftBox\Requisites\Contract\StorageAdapterInterface;
use PhpSoftBox\Requisites\DTO\RequisitesRecord;
use PhpSoftBox\Requisites\DTO\RequisitesSaveResult;
use PhpSoftBox\Requisites\DTO\RequisitesSchema;
use PhpSoftBox\Requisites\DTO\RequisitesSubject;
use PhpSoftBox\Requisites\Exception\RequisitesValidationFailedException;
use PhpSoftBox\Requisites\Exception\SelectorMismatchException;
use PhpSoftBox\Requisites\Exception\StorageException;
use PhpSoftBox\Requisites\Migration\MigrationTargetVersionResolverInterface;
use PhpSoftBox\Requisites\Migration\PayloadMigrationEngine;
use PhpSoftBox\Requisites\Schema\SchemaFieldResolver;
use PhpSoftBox\Validator\ValidationResult;

use function is_string;
use function sprintf;

final readonly class DefaultRequisitesManager implements ManagedRequisitesManagerInterface
{
    public function __construct(
        private RequisitesProfileRegistryInterface $profiles,
        private StorageAdapterInterface $storage,
        private SelectorResolverInterface $selectorResolver,
        private SchemaProviderInterface $schemaProvider,
        private SchemaFieldResolver $schemaFieldResolver,
        private RequisitesValidatorInterface $validator,
        private PayloadMigrationEngine $migrationEngine,
        private MigrationTargetVersionResolverInterface $targetVersionResolver,
    ) {
    }

    public function load(
        RequisitesSubject $subject,
        string $profile,
        array $context = [],
    ): RequisitesRecord {
        $this->profiles->get($profile);

        $record = $this->storage->find($subject, $profile);
        if ($record !== null) {
            $this->assertExistingSelector($record, [], $context);

            return $this->migrate($record);
        }

        $draft    = $this->storage->create($subject, $profile);
        $selector = $this->selectorResolver->resolve($profile, $context, []);
        $version  = $this->targetVersionResolver->targetVersion($profile, $selector);

        return $this->copy($draft, selector: $selector, schemaVersion: $version);
    }

    public function validate(
        RequisitesRecord $record,
        array $payload,
        array $context = [],
    ): ValidationResult {
        $selector = $this->selectorFor($record, $payload, $context);

        return $this->validator->validate($record->profile, $selector, $payload);
    }

    public function save(
        RequisitesRecord $record,
        array $payload,
        array $context = [],
    ): RequisitesRecord {
        $result = $this->validateAndSave($record, $payload, $context);
        if (!$result->saved) {
            throw new RequisitesValidationFailedException($result->validation);
        }

        return $result->record;
    }

    public function validateAndSave(
        RequisitesRecord $record,
        array $payload,
        array $context = [],
    ): RequisitesSaveResult {
        $this->profiles->get($record->profile);
        $selector   = $this->selectorFor($record, $payload, $context);
        $validation = $this->validator->validate($record->profile, $selector, $payload);
        if ($validation->hasErrors()) {
            return new RequisitesSaveResult($record, $validation, false);
        }

        $targetVersion = $this->targetVersionResolver->targetVersion($record->profile, $selector);
        $toSave        = $this->copy(
            $record,
            selector: $selector,
            schemaVersion: $targetVersion,
            payload: $validation->filteredData(),
        );
        if ($toSave->id === null) {
            if (!$this->storage instanceof AtomicCreateStorageInterface) {
                throw new StorageException('Managed requisites storage must support atomic create.');
            }

            $createResult = $this->storage->insertOrFind($toSave);
            if ($createResult->inserted) {
                $toSave = $createResult->record;
            } else {
                $this->assertSelectorValue($createResult->record, $selector, 'concurrent record');
                $toSave = $this->copy($toSave, id: $createResult->record->id);
                $this->storage->save($toSave);
            }
        } else {
            $this->storage->save($toSave);
        }

        $saved = $this->storage->find(
            new RequisitesSubject($toSave->subjectType, $toSave->subjectId),
            $toSave->profile,
        ) ?? $toSave;

        return new RequisitesSaveResult($saved, $validation, true);
    }

    public function schema(
        RequisitesRecord $record,
        array $context = [],
    ): RequisitesSchema {
        $selector = $this->selectorFor($record, [], $context);
        $schema   = $this->schemaProvider->schema($record->profile, $selector);

        return $this->schemaFieldResolver->resolve($schema, $record->payload);
    }

    public function migrate(RequisitesRecord $record): RequisitesRecord
    {
        $targetVersion = $this->targetVersionResolver->targetVersion($record->profile, $record->selector);
        $migrated      = $this->migrationEngine->migrate($record, $targetVersion);
        if ($migrated->schemaVersion !== $record->schemaVersion && $migrated->id !== null) {
            $this->storage->save($migrated);
        }

        return $migrated;
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $context
     */
    private function selectorFor(RequisitesRecord $record, array $payload, array $context): string
    {
        $this->profiles->get($record->profile);
        if ($record->id === null) {
            return $this->selectorResolver->resolve($record->profile, $context, $payload);
        }

        $this->assertExistingSelector($record, $payload, $context);

        return $record->selector;
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $context
     */
    private function assertExistingSelector(RequisitesRecord $record, array $payload, array $context): void
    {
        $profile     = $this->profiles->get($record->profile);
        $selectorKey = $profile->selectorKey();

        foreach (['operation context' => $context, 'payload' => $payload] as $source => $values) {
            $candidate = $values[$selectorKey] ?? null;
            if (!is_string($candidate) || $candidate === '') {
                continue;
            }
            $this->assertSelectorValue($record, $candidate, $source);
        }
    }

    private function assertSelectorValue(RequisitesRecord $record, string $candidate, string $source): void
    {
        if ($candidate === $record->selector) {
            return;
        }

        throw new SelectorMismatchException(sprintf(
            'Selector "%s" from %s does not match stored selector "%s" for profile "%s".',
            $candidate,
            $source,
            $record->selector,
            $record->profile,
        ));
    }

    /**
     * @param array<string, mixed>|null $payload
     */
    private function copy(
        RequisitesRecord $record,
        ?string $selector = null,
        ?int $schemaVersion = null,
        ?array $payload = null,
        int|string|null $id = null,
    ): RequisitesRecord {
        return new RequisitesRecord(
            profile: $record->profile,
            selector: $selector ?? $record->selector,
            schemaVersion: $schemaVersion ?? $record->schemaVersion,
            subjectType: $record->subjectType,
            subjectId: $record->subjectId,
            payload: $payload ?? $record->payload,
            attachments: $record->attachments,
            id: $id ?? $record->id,
        );
    }
}
