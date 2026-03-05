<?php

declare(strict_types=1);

namespace PhpSoftBox\Requisites\Tests\Profile;

use PhpSoftBox\Forms\DTO\FormDefinition;
use PhpSoftBox\Requisites\DTO\RequisitesSchema;
use PhpSoftBox\Requisites\Exception\DuplicateProfileException;
use PhpSoftBox\Requisites\Exception\MissingProfileValidatorException;
use PhpSoftBox\Requisites\Exception\ProfileNotFoundException;
use PhpSoftBox\Requisites\Profile\ArrayRequisitesProfileRegistry;
use PhpSoftBox\Requisites\Profile\ProfileStorageDefinition;
use PhpSoftBox\Requisites\Tests\Support\TestRequisitesProfile;
use PhpSoftBox\Validator\FormValidationInterface;
use PhpSoftBox\Validator\ValidationOptions;
use PhpSoftBox\Validator\ValidationResult;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function array_keys;

#[CoversClass(ArrayRequisitesProfileRegistry::class)]
final class ArrayRequisitesProfileRegistryTest extends TestCase
{
    #[Test]
    public function registersProfilesInRegistrationOrder(): void
    {
        $first    = self::profile('first');
        $second   = self::profile('second');
        $registry = new ArrayRequisitesProfileRegistry([$first, $second]);

        self::assertSame(['first', 'second'], array_keys($registry->all()));
        self::assertTrue($registry->has('first'));
        self::assertSame($second, $registry->get('second'));
    }

    #[Test]
    public function rejectsDuplicateProfile(): void
    {
        $this->expectException(DuplicateProfileException::class);

        new ArrayRequisitesProfileRegistry([self::profile('company'), self::profile('company')]);
    }

    #[Test]
    public function rejectsUnknownProfile(): void
    {
        $registry = new ArrayRequisitesProfileRegistry([self::profile('company')]);

        $this->expectException(ProfileNotFoundException::class);
        $registry->get('person');
    }

    #[Test]
    public function rejectsMissingValidatorAtBuildTime(): void
    {
        $profile = new TestRequisitesProfile(
            name: 'company',
            schemas: ['default' => self::schema('company')],
            targetVersions: 1,
            validators: [],
            storage: ProfileStorageDefinition::default(),
        );

        $this->expectException(MissingProfileValidatorException::class);
        new ArrayRequisitesProfileRegistry([$profile]);
    }

    private static function profile(string $name): TestRequisitesProfile
    {
        return new TestRequisitesProfile(
            name: $name,
            schemas: ['default' => self::schema($name)],
            targetVersions: 1,
            validators: ['default' => RegistryValidForm::class],
            storage: ProfileStorageDefinition::default(),
        );
    }

    private static function schema(string $profile): RequisitesSchema
    {
        return new RequisitesSchema(
            profile: $profile,
            selector: 'default',
            version: 1,
            form: new FormDefinition($profile, $profile),
        );
    }
}

final class RegistryValidForm implements FormValidationInterface
{
    /** @param array<string, mixed> $payload */
    public function __construct(
        private readonly array
    $payload)
    {
    }

    public function beforeValidation(): void
    {
    }

    public function validate(?ValidationOptions $options = null): array
    {
        return $this->payload;
    }

    public function validationResult(?ValidationOptions $options = null): ValidationResult
    {
        return new ValidationResult([], $this->payload);
    }
}
