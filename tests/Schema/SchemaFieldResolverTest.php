<?php

declare(strict_types=1);

namespace PhpSoftBox\Requisites\Tests\Schema;

use PhpSoftBox\Forms\DTO\FormDefinition;
use PhpSoftBox\Forms\DTO\FormFieldDefinition;
use PhpSoftBox\Forms\FormFieldTypesEnum;
use PhpSoftBox\Requisites\DTO\RequisitesSchema;
use PhpSoftBox\Requisites\Schema\SchemaFieldResolver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(SchemaFieldResolver::class)]
final class SchemaFieldResolverTest extends TestCase
{
    /**
     * Проверяет: visibleWhen скрывает поля, если условия не выполнены.
     */
    #[Test]
    public function visibleWhenFiltersFields(): void
    {
        $resolver = new SchemaFieldResolver();
        $schema   = new RequisitesSchema(
            profile: 'company',
            selector: 'country:RU',
            version: 1,
            form: new FormDefinition(
                id: 'company.requisites',
                title: 'Company requisites',
                fields: [
                    new FormFieldDefinition('org_type', 'Org type', FormFieldTypesEnum::SELECT, true),
                    new FormFieldDefinition(
                        key: 'kpp',
                        label: 'KPP',
                        fieldType: FormFieldTypesEnum::TEXT,
                        visibleWhen: [
                            ['field' => 'org_type', 'operator' => '=', 'value' => 'legal'],
                        ],
                    ),
                ],
            ),
        );

        $resolved = $resolver->resolve($schema, ['org_type' => 'individual']);

        $this->assertCount(1, $resolved->form->fields);
        $this->assertSame('org_type', $resolved->form->fields[0]->key);
    }

    /**
     * Проверяет: requiredWhen динамически переопределяет required у поля.
     */
    #[Test]
    public function requiredWhenOverridesRequiredFlag(): void
    {
        $resolver = new SchemaFieldResolver();
        $schema   = new RequisitesSchema(
            profile: 'company',
            selector: 'country:RU',
            version: 1,
            form: new FormDefinition(
                id: 'company.requisites',
                title: 'Company requisites',
                fields: [
                    new FormFieldDefinition(
                        key: 'kpp',
                        label: 'KPP',
                        fieldType: FormFieldTypesEnum::TEXT,
                        requiredWhen: [
                            ['field' => 'org_type', 'operator' => '=', 'value' => 'legal'],
                        ],
                    ),
                ],
            ),
        );

        $forLegal = $resolver->resolve($schema, ['org_type' => 'legal']);
        $forIp    = $resolver->resolve($schema, ['org_type' => 'individual']);

        $this->assertTrue($forLegal->form->fields[0]->required);
        $this->assertFalse($forIp->form->fields[0]->required);
    }

    /**
     * Проверяет: list-условия поддерживают оператор in.
     */
    #[Test]
    public function supportsListConditionsWithInOperator(): void
    {
        $resolver = new SchemaFieldResolver();
        $schema   = new RequisitesSchema(
            profile: 'company',
            selector: 'default',
            version: 1,
            form: new FormDefinition(
                id: 'company.requisites',
                title: 'Company requisites',
                fields: [
                    new FormFieldDefinition(
                        key: 'regional_code',
                        label: 'Regional code',
                        fieldType: FormFieldTypesEnum::TEXT,
                        required: true,
                        visibleWhen: [
                            [
                                'field'    => 'country',
                                'operator' => 'in',
                                'value'    => ['RU', 'KZ'],
                            ],
                        ],
                    ),
                ],
            ),
        );

        $ru = $resolver->resolve($schema, ['country' => 'RU']);
        $us = $resolver->resolve($schema, ['country' => 'US']);

        $this->assertCount(1, $ru->form->fields);
        $this->assertCount(0, $us->form->fields);
    }

    /**
     * Проверяет: неизвестный оператор в условии делает условие невыполненным.
     */
    #[Test]
    public function unknownOperatorFailsCondition(): void
    {
        $resolver = new SchemaFieldResolver();
        $schema   = new RequisitesSchema(
            profile: 'company',
            selector: 'default',
            version: 1,
            form: new FormDefinition(
                id: 'company.requisites',
                title: 'Company requisites',
                fields: [
                    new FormFieldDefinition(
                        key: 'x',
                        label: 'X',
                        fieldType: FormFieldTypesEnum::TEXT,
                        visibleWhen: [
                            [
                                'field'    => 'country',
                                'operator' => 'contains',
                                'value'    => 'RU',
                            ],
                        ],
                    ),
                ],
            ),
        );

        $resolved = $resolver->resolve($schema, ['country' => 'RU']);

        $this->assertCount(0, $resolved->form->fields);
    }
}
