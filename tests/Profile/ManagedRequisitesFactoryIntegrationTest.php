<?php

declare(strict_types=1);

namespace PhpSoftBox\Requisites\Tests\Profile;

use PhpSoftBox\Database\SchemaBuilder\TableBlueprint;
use PhpSoftBox\Forms\DTO\FormDefinition;
use PhpSoftBox\Requisites\DTO\RequisitesSchema;
use PhpSoftBox\Requisites\DTO\RequisitesSubject;
use PhpSoftBox\Requisites\Profile\ArrayRequisitesProfileRegistry;
use PhpSoftBox\Requisites\Profile\ManagedRequisitesFactory;
use PhpSoftBox\Requisites\Profile\ProfileStorageDefinition;
use PhpSoftBox\Requisites\Schema\SelectorResolutionPolicy;
use PhpSoftBox\Requisites\Tests\Support\IntegrationDatabases;
use PhpSoftBox\Requisites\Tests\Support\TestRequisitesProfile;
use PhpSoftBox\Validator\FormValidationInterface;
use PhpSoftBox\Validator\ValidationOptions;
use PhpSoftBox\Validator\ValidationResult;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Throwable;

#[CoversClass(ManagedRequisitesFactory::class)]
final class ManagedRequisitesFactoryIntegrationTest extends TestCase
{
    #[Test]
    public function buildsCompleteManagerAndBackfillFlow(): void
    {
        try {
            $database = IntegrationDatabases::sqliteDatabase();
        } catch (Throwable $exception) {
            self::markTestSkipped($exception->getMessage());
        }

        $database->execute('DROP TABLE IF EXISTS managed_requisites');
        $database->connection()->schema()->create('managed_requisites', static function (TableBlueprint $table): void {
            $table->id();
            $table->string('subject_type', 120);
            $table->string('subject_id', 64);
            $table->string('profile', 80);
            $table->string('selector', 120);
            $table->json('payload_json')->nullable();
            $table->json('attachments_json')->nullable();
            $table->integer('schema_version');
            $table->datetime('created_datetime');
            $table->datetime('updated_datetime');
            $table->unique(['subject_type', 'subject_id', 'profile'], 'managed_requisites_unique');
        });

        $schema = new RequisitesSchema(
            profile: 'company',
            selector: 'default',
            version: 1,
            form: new FormDefinition('company', 'Company'),
        );

        $profile = new TestRequisitesProfile(
            name: 'company',
            schemas: ['default' => $schema],
            targetVersions: 1,
            validators: ['default' => FactoryPassThroughForm::class],
            storage: ProfileStorageDefinition::default(table: 'managed_requisites'),
        );

        $profiles = new ArrayRequisitesProfileRegistry([$profile]);

        try {
            $factory = new ManagedRequisitesFactory($database->manager());

            $policies = ['company' => SelectorResolutionPolicy::CONTEXT_ONLY];
            $manager  = $factory->createManager($profiles, $policies);
            $draft    = $manager->load(new RequisitesSubject('company', 'owner-1'), 'company');
            $saved    = $manager->validateAndSave($draft, ['name' => 'Acme', 'ignored' => true]);

            self::assertTrue($saved->saved);
            self::assertNotNull($saved->record->id);
            self::assertSame(['name' => 'Acme'], $saved->record->payload);

            $report = $factory->createBackfillRunner($profiles)->run('company', dryRun: true);
            self::assertSame('managed_requisites', $report->table);
            self::assertSame('default', $report->storageDriver);
            self::assertSame(1, $report->processed);
            self::assertSame(1, $report->skipped);
        } finally {
            $database->execute('DROP TABLE IF EXISTS managed_requisites');
        }
    }
}

final class FactoryPassThroughForm implements FormValidationInterface
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
        return new ValidationResult([], ['name' => (string) ($this->payload['name'] ?? '')]);
    }
}
