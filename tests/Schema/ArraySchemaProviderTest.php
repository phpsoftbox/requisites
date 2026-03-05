<?php

declare(strict_types=1);

namespace PhpSoftBox\Requisites\Tests\Schema;

use PhpSoftBox\Forms\DTO\FormDefinition;
use PhpSoftBox\Requisites\DTO\RequisitesSchema;
use PhpSoftBox\Requisites\Exception\SchemaNotFoundException;
use PhpSoftBox\Requisites\Schema\ArraySchemaProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ArraySchemaProvider::class)]
final class ArraySchemaProviderTest extends TestCase
{
    /**
     * Проверяет: схема возвращается по точному selector.
     */
    #[Test]
    public function returnsSchemaByExactSelector(): void
    {
        $ru = new RequisitesSchema('company', 'country:RU', 1, new FormDefinition('company.ru', 'Company RU'));

        $provider = new ArraySchemaProvider([
                    'company' => [
                        'country:RU' => $ru,
                    ],
                ]);

        $schema = $provider->schema('company', 'country:RU');

        $this->assertSame($ru, $schema);
    }

    /**
     * Проверяет: если selector не найден, используется default схема профиля.
     */
    #[Test]
    public function fallsBackToDefaultSchema(): void
    {
        $default = new RequisitesSchema('company', 'default', 1, new FormDefinition('company.default', 'Company'));

        $provider = new ArraySchemaProvider([
                    'company' => [
                        'default' => $default,
                    ],
                ]);

        $schema = $provider->schema('company', 'country:UZ');

        $this->assertSame($default, $schema);
    }

    /**
     * Проверяет: отсутствие профиля/схемы приводит к явной ошибке.
     */
    #[Test]
    public function throwsWhenSchemaIsMissing(): void
    {
        $provider = new ArraySchemaProvider([]);

        $this->expectException(SchemaNotFoundException::class);

        $provider->schema('company', 'country:RU');
    }
}
