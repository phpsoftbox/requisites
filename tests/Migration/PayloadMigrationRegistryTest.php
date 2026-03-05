<?php

declare(strict_types=1);

namespace PhpSoftBox\Requisites\Tests\Migration;

use InvalidArgumentException;
use PhpSoftBox\Requisites\Migration\PayloadMigrationRegistry;
use PhpSoftBox\Requisites\Tests\Support\StepMigrator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(PayloadMigrationRegistry::class)]
final class PayloadMigrationRegistryTest extends TestCase
{
    /**
     * Проверяет: при одинаковой версии source/target цепочка миграции пуста.
     */
    #[Test]
    public function chainIsEmptyWhenVersionsAreEqual(): void
    {
        $registry = new PayloadMigrationRegistry([]);

        $chain = $registry->chain('company', 'country:RU', 2, 2);

        $this->assertSame([], $chain);
    }

    /**
     * Проверяет: registry строит пошаговую цепочку N->N+1->...->M.
     */
    #[Test]
    public function chainBuildsStepByStepMigrators(): void
    {
        $m1 = new StepMigrator('company', 'country:RU', 1, 2, 'a');
        $m2 = new StepMigrator('company', 'country:RU', 2, 3, 'b');
        $m3 = new StepMigrator('company', 'country:RU', 3, 4, 'c');

        $registry = new PayloadMigrationRegistry([$m1, $m2, $m3]);

        $chain = $registry->chain('company', 'country:RU', 1, 4);

        $this->assertSame([$m1, $m2, $m3], $chain);
    }

    /**
     * Проверяет: при отсутствии нужного шага выбрасывается ошибка конфигурации.
     */
    #[Test]
    public function missingStepThrowsException(): void
    {
        $registry = new PayloadMigrationRegistry([
            new StepMigrator('company', 'country:RU', 1, 2, 'a'),
            new StepMigrator('company', 'country:RU', 3, 4, 'c'),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid migrator configuration');

        $registry->chain('company', 'country:RU', 1, 4);
    }

    /**
     * Проверяет: неоднозначное совпадение шага (2 мигратора на один шаг) запрещено.
     */
    #[Test]
    public function duplicateStepThrowsException(): void
    {
        $registry = new PayloadMigrationRegistry([
            new StepMigrator('company', 'country:RU', 1, 2, 'a'),
            new StepMigrator('company', 'country:RU', 1, 2, 'b'),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid migrator configuration');

        $registry->chain('company', 'country:RU', 1, 2);
    }

    /**
     * Проверяет: некорректный диапазон версий отклоняется на уровне аргументов.
     */
    #[Test]
    public function invalidVersionRangeThrowsException(): void
    {
        $registry = new PayloadMigrationRegistry([]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('From version must be less than or equal');

        $registry->chain('company', 'country:RU', 3, 2);
    }
}
