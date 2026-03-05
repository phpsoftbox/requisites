<?php

declare(strict_types=1);

namespace PhpSoftBox\Requisites\Tests\Migration;

use InvalidArgumentException;
use PhpSoftBox\Requisites\Migration\StaticTargetVersionResolver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(StaticTargetVersionResolver::class)]
final class StaticTargetVersionResolverTest extends TestCase
{
    /**
     * Проверяет: resolver поддерживает как profile-level, так и selector-level конфиг.
     */
    #[Test]
    public function resolvesProfileAndSelectorTargetVersions(): void
    {
        $resolver = new StaticTargetVersionResolver([
            'passport' => 2,
            'company'  => [
                'country:RU' => 4,
                'default'    => 3,
            ],
        ]);

        self::assertSame(2, $resolver->targetVersion('passport', 'default'));
        self::assertSame(4, $resolver->targetVersion('company', 'country:RU'));
        self::assertSame(3, $resolver->targetVersion('company', 'country:KZ'));
    }

    /**
     * Проверяет: отсутствие profile/selector конфигурации приводит к явной ошибке.
     */
    #[Test]
    public function missingConfigurationThrowsException(): void
    {
        $resolver = new StaticTargetVersionResolver([
            'company' => [
                'country:RU' => 2,
            ],
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Target version not found');

        $resolver->targetVersion('company', 'country:KZ');
    }

    /**
     * Проверяет: target version меньше 1 не допускается.
     */
    #[Test]
    public function invalidVersionThrowsException(): void
    {
        $resolver = new StaticTargetVersionResolver([
            'company' => 0,
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Target version must be >= 1');

        $resolver->targetVersion('company', 'default');
    }
}
