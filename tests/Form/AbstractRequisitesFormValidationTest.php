<?php

declare(strict_types=1);

namespace PhpSoftBox\Requisites\Tests\Form;

use InvalidArgumentException;
use PhpSoftBox\Requisites\Form\AbstractRequisitesFormValidation;
use PhpSoftBox\Validator\Exception\ValidationException;
use PhpSoftBox\Validator\Filter\TrimFilter;
use PhpSoftBox\Validator\Filter\UppercaseFilter;
use PhpSoftBox\Validator\Rule\StringValidation;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(AbstractRequisitesFormValidation::class)]
final class AbstractRequisitesFormValidationTest extends TestCase
{
    #[Test]
    public function beforeValidationAppliesFiltersAndValidationUsesNormalizedPayload(): void
    {
        $form = new DummyRequisitesFormValidation([
            'name'    => '  test  ',
            'country' => ' ru ',
        ]);

        $result = $form->validationResult();

        self::assertFalse($result->hasErrors());
        self::assertSame('test', $result->filteredData()['name'] ?? null);
        self::assertSame('RU', $result->filteredData()['country'] ?? null);
    }

    #[Test]
    public function filterErrorsAreReturnedAsValidationErrors(): void
    {
        $form = new DummyRequisitesFormValidation([
            'name'       => 'test',
            'country'    => 'ru',
            'broken'     => 'x',
            'use_broken' => true,
        ]);

        $result = $form->validationResult();

        self::assertTrue($result->hasErrors());
        self::assertArrayHasKey('broken', $result->errors());

        $this->expectException(ValidationException::class);
        $form->validate();
    }
}

final class DummyRequisitesFormValidation extends AbstractRequisitesFormValidation
{
    public function rules(): array
    {
        return [
            'name' => [
                new StringValidation()->required()->min(2),
            ],
            'country' => [
                new StringValidation()->required()->in('RU'),
            ],
        ];
    }

    public function beforeValidation(): void
    {
        $this->applyFilters([
            'name'    => [new TrimFilter()],
            'country' => [new TrimFilter(), new UppercaseFilter()],
        ]);

        if (($this->payload()['use_broken'] ?? false) === true) {
            $this->applyFilters([
                'broken' => static function (mixed $value): mixed {
                    throw new InvalidArgumentException('Broken filter.');
                },
            ]);
        }
    }
}
