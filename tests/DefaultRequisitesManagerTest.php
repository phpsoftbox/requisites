<?php

declare(strict_types=1);

namespace PhpSoftBox\Requisites\Tests;

use PhpSoftBox\Forms\DTO\FormDefinition;
use PhpSoftBox\Requisites\Contract\AtomicCreateStorageInterface;
use PhpSoftBox\Requisites\DefaultRequisitesManager;
use PhpSoftBox\Requisites\DTO\RequisitesInsertResult;
use PhpSoftBox\Requisites\DTO\RequisitesRecord;
use PhpSoftBox\Requisites\DTO\RequisitesSchema;
use PhpSoftBox\Requisites\DTO\RequisitesSubject;
use PhpSoftBox\Requisites\Exception\RequisitesValidationFailedException;
use PhpSoftBox\Requisites\Exception\SelectorMismatchException;
use PhpSoftBox\Requisites\Migration\PayloadMigrationEngine;
use PhpSoftBox\Requisites\Migration\PayloadMigrationRegistry;
use PhpSoftBox\Requisites\Migration\StaticTargetVersionResolver;
use PhpSoftBox\Requisites\Profile\ArrayRequisitesProfileRegistry;
use PhpSoftBox\Requisites\Profile\ProfileStorageDefinition;
use PhpSoftBox\Requisites\Schema\ArraySchemaProvider;
use PhpSoftBox\Requisites\Schema\FallbackSelectorResolver;
use PhpSoftBox\Requisites\Schema\SchemaFieldResolver;
use PhpSoftBox\Requisites\Schema\SelectorResolutionPolicy;
use PhpSoftBox\Requisites\Tests\Support\TestRequisitesProfile;
use PhpSoftBox\Requisites\Validation\FormValidationRequisitesValidator;
use PhpSoftBox\Validator\FormValidationInterface;
use PhpSoftBox\Validator\ValidationError;
use PhpSoftBox\Validator\ValidationOptions;
use PhpSoftBox\Validator\ValidationResult;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function is_string;
use function trim;

#[CoversClass(DefaultRequisitesManager::class)]
final class DefaultRequisitesManagerTest extends TestCase
{
    #[Test]
    public function contextOnlyFlowFiltersPayloadAndReturnsPersistedRecord(): void
    {
        [$manager, $storage] = self::manager();
        $draft               = $manager->load(new RequisitesSubject('company', 'owner-1'), 'company', ['mode' => 'ru']);

        self::assertSame('ru', $draft->selector);
        self::assertSame(2, $draft->schemaVersion);

        $result = $manager->validateAndSave(
            $draft,
            ['mode' => 'request-controlled', 'name' => ' Acme ', 'ignored' => true],
            ['mode' => 'ru'],
        );

        self::assertTrue($result->saved);
        self::assertSame('row-1', $result->record->id);
        self::assertSame('ru', $result->record->selector);
        self::assertSame(['name' => 'Acme'], $result->record->payload);
        self::assertSame(1, $storage->saveCount);
    }

    #[Test]
    public function validationFailureDoesNotWrite(): void
    {
        [$manager, $storage] = self::manager();
        $draft               = $manager->load(new RequisitesSubject('company', 2), 'company', ['mode' => 'ru']);

        $result = $manager->validateAndSave($draft, [], ['mode' => 'ru']);

        self::assertFalse($result->saved);
        self::assertTrue($result->validation->hasErrors());
        self::assertSame(0, $storage->saveCount);
    }

    #[Test]
    public function legacySaveFailsExplicitlyOnInvalidPayload(): void
    {
        [$manager] = self::manager();
        $draft     = $manager->load(new RequisitesSubject('company', 3), 'company', ['mode' => 'ru']);

        $this->expectException(RequisitesValidationFailedException::class);
        $manager->save($draft, [], ['mode' => 'ru']);
    }

    #[Test]
    public function existingSelectorCannotBeChangedByContextOrPayload(): void
    {
        [$manager] = self::manager();
        $saved     = $manager->save(
            $manager->load(new RequisitesSubject('company', 4), 'company', ['mode' => 'ru']),
            ['name' => 'Acme'],
            ['mode' => 'ru'],
        );

        try {
            $manager->validate($saved, ['name' => 'Acme'], ['mode' => 'kz']);
            self::fail('Context selector mismatch was not detected.');
        } catch (SelectorMismatchException) {
        }

        $this->expectException(SelectorMismatchException::class);
        $manager->validate($saved, ['mode' => 'kz', 'name' => 'Acme'], ['mode' => 'ru']);
    }

    #[Test]
    public function concurrentCreateCannotOverwriteAnotherSelector(): void
    {
        [$manager, $storage] = self::manager();
        $subject             = new RequisitesSubject('company', 5);

        $draft = $manager->load($subject, 'company', ['mode' => 'ru']);

        $storage->seed(new RequisitesRecord(
            profile: 'company',
            selector: 'default',
            schemaVersion: 1,
            subjectType: 'company',
            subjectId: 5,
            payload: ['name' => 'Concurrent'],
            id: 'concurrent-row',
        ));

        try {
            $manager->validateAndSave($draft, ['name' => 'Requested'], ['mode' => 'ru']);
            self::fail('Concurrent selector mismatch was not detected.');
        } catch (SelectorMismatchException) {
        }

        $canonical = $storage->find($subject, 'company');
        self::assertSame('default', $canonical?->selector);
        self::assertSame(['name' => 'Concurrent'], $canonical?->payload);
    }

    /** @return array{DefaultRequisitesManager, InMemoryRequisitesStorage} */
    private static function manager(): array
    {
        $schemas = [
            'default' => self::schema('default', 1),
            'ru'      => self::schema('ru', 2),
        ];
        $profile = new TestRequisitesProfile(
            name: 'company',
            schemas: $schemas,
            targetVersions: ['default' => 1, 'ru' => 2],
            validators: ['default' => ManagedTestForm::class],
            storage: ProfileStorageDefinition::default(),
            selectorKeyValue: 'mode',
        );

        $profiles      = new ArrayRequisitesProfileRegistry([$profile]);
        $storage       = new InMemoryRequisitesStorage();
        $migration     = new PayloadMigrationEngine(new PayloadMigrationRegistry());
        $targetVersion = new StaticTargetVersionResolver(['company' => ['default' => 1, 'ru' => 2]]);

        return [
            new DefaultRequisitesManager(
                profiles: $profiles,
                storage: $storage,
                selectorResolver: new FallbackSelectorResolver(
                    selectorKey: 'mode',
                    policy: SelectorResolutionPolicy::CONTEXT_ONLY,
                ),
                schemaProvider: new ArraySchemaProvider(['company' => $schemas]),
                schemaFieldResolver: new SchemaFieldResolver(),
                validator: new FormValidationRequisitesValidator([
                    'company' => ['default' => ManagedTestForm::class],
                ], strict: true),
                migrationEngine: $migration,
                targetVersionResolver: $targetVersion,
            ),
            $storage,
        ];
    }

    private static function schema(string $selector, int $version): RequisitesSchema
    {
        return new RequisitesSchema(
            profile: 'company',
            selector: $selector,
            version: $version,
            form: new FormDefinition('company-' . $selector, 'Company'),
        );
    }
}

final class InMemoryRequisitesStorage implements AtomicCreateStorageInterface
{
    /** @var array<string, RequisitesRecord> */
    private array $records = [];
    public int $saveCount  = 0;

    public function find(RequisitesSubject $subject, string $profile): ?RequisitesRecord
    {
        return $this->records[$this->key($subject, $profile)] ?? null;
    }

    public function create(RequisitesSubject $subject, string $profile): RequisitesRecord
    {
        return new RequisitesRecord($profile, 'default', 1, $subject->type, $subject->id);
    }

    public function save(RequisitesRecord $record): void
    {
        ++$this->saveCount;
        $saved = new RequisitesRecord(
            profile: $record->profile,
            selector: $record->selector,
            schemaVersion: $record->schemaVersion,
            subjectType: $record->subjectType,
            subjectId: $record->subjectId,
            payload: $record->payload,
            attachments: $record->attachments,
            id: $record->id ?? 'row-' . $this->saveCount,
        );

        $this->records[$this->key(
            new RequisitesSubject($record->subjectType, $record->subjectId),
            $record->profile,
        )] = $saved;
    }

    public function insertOrFind(RequisitesRecord $record): RequisitesInsertResult
    {
        $subject = new RequisitesSubject($record->subjectType, $record->subjectId);

        $existing = $this->find($subject, $record->profile);
        if ($existing !== null) {
            return new RequisitesInsertResult($existing, false);
        }

        $this->save($record);

        return new RequisitesInsertResult(
            $this->find($subject, $record->profile) ?? $record,
            true,
        );
    }

    public function seed(RequisitesRecord $record): void
    {
        $this->records[$this->key(
            new RequisitesSubject($record->subjectType, $record->subjectId),
            $record->profile,
        )] = $record;
    }

    private function key(RequisitesSubject $subject, string $profile): string
    {
        return $subject->type . ':' . $subject->id . ':' . $profile;
    }
}

final class ManagedTestForm implements FormValidationInterface
{
    /** @param array<string, mixed> $payload */
    public function __construct(
        private readonly array
    $payload,
    ) {
    }

    public function beforeValidation(): void
    {
    }

    public function validate(?ValidationOptions $options = null): array
    {
        return $this->validationResult($options)->filteredData();
    }

    public function validationResult(?ValidationOptions $options = null): ValidationResult
    {
        $name = $this->payload['name'] ?? null;
        if (!is_string($name) || trim($name) === '') {
            return new ValidationResult([
                'name' => [new ValidationError('name', 'required', 'Name is required.')],
            ], []);
        }

        return new ValidationResult([], ['name' => trim($name)]);
    }
}
