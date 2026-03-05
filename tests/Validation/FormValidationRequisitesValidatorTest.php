<?php

declare(strict_types=1);

namespace PhpSoftBox\Requisites\Tests\Validation;

use PhpSoftBox\Requisites\Validation\FormValidationRequisitesValidator;
use PhpSoftBox\Validator\FormValidationInterface;
use PhpSoftBox\Validator\ValidationError;
use PhpSoftBox\Validator\ValidationOptions;
use PhpSoftBox\Validator\ValidationResult as FormValidationResult;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(FormValidationRequisitesValidator::class)]
final class FormValidationRequisitesValidatorTest extends TestCase
{
    #[Test]
    public function returnsSuccessForConfiguredForm(): void
    {
        $validator = new FormValidationRequisitesValidator([
            'company' => [
                'default' => DummyValidFormValidation::class,
            ],
        ]);

        $result = $validator->validate('company', 'default', ['name' => 'Acme']);

        self::assertFalse($result->hasErrors());
        self::assertSame(['name' => 'Acme'], $result->filteredData());
    }

    #[Test]
    public function returnsFailureWhenFormHasErrors(): void
    {
        $validator = new FormValidationRequisitesValidator([
            'company' => [
                'default' => DummyInvalidFormValidation::class,
            ],
        ]);

        $result = $validator->validate('company', 'default', ['name' => 'x']);

        self::assertTrue($result->hasErrors());
        self::assertArrayHasKey('name', $result->errors());
    }
}

final class DummyValidFormValidation implements FormValidationInterface
{
    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        private readonly array $payload,
    ) {
    }

    public function beforeValidation(): void
    {
    }

    public function validate(?ValidationOptions $options = null): array
    {
        return $this->payload;
    }

    public function validationResult(?ValidationOptions $options = null): FormValidationResult
    {
        return new FormValidationResult([], $this->payload);
    }
}

final class DummyInvalidFormValidation implements FormValidationInterface
{
    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        private readonly array $payload,
    ) {
    }

    public function beforeValidation(): void
    {
    }

    public function validate(?ValidationOptions $options = null): array
    {
        return $this->payload;
    }

    public function validationResult(?ValidationOptions $options = null): FormValidationResult
    {
        return new FormValidationResult([
            'name' => [new ValidationError('name', 'required', 'Field name is required.')],
        ], []);
    }
}
