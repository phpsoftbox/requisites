<?php

declare(strict_types=1);

namespace PhpSoftBox\Requisites\Tests\Schema;

use PhpSoftBox\Requisites\Schema\FallbackSelectorResolver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(FallbackSelectorResolver::class)]
final class FallbackSelectorResolverTest extends TestCase
{
    /**
     * Проверяет: selector из payload имеет высший приоритет.
     */
    #[Test]
    public function payloadSelectorHasPriority(): void
    {
        $resolver = new FallbackSelectorResolver('selector', 'default');

        $selector = $resolver->resolve(
            profile: 'company',
            context: ['selector' => 'country:KZ'],
            payload: ['selector' => 'country:RU'],
        );

        $this->assertSame('country:RU', $selector);
    }

    /**
     * Проверяет: при пустом payload selector берется из context.
     */
    #[Test]
    public function contextSelectorUsedAsFallback(): void
    {
        $resolver = new FallbackSelectorResolver('selector', 'default');

        $selector = $resolver->resolve(
            profile: 'company',
            context: ['selector' => 'country:KZ'],
            payload: [],
        );

        $this->assertSame('country:KZ', $selector);
    }

    /**
     * Проверяет: если selector не найден, возвращается default.
     */
    #[Test]
    public function defaultSelectorUsedWhenMissing(): void
    {
        $resolver = new FallbackSelectorResolver('selector', 'default');

        $selector = $resolver->resolve(
            profile: 'company',
            context: [],
            payload: [],
        );

        $this->assertSame('default', $selector);
    }
}
