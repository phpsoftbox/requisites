<?php

declare(strict_types=1);

namespace PhpSoftBox\Requisites\Tests\Schema;

use PhpSoftBox\Requisites\Schema\FallbackSelectorResolver;
use PhpSoftBox\Requisites\Schema\SelectorResolutionPolicy;
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

    #[Test]
    public function supportsContextFirstPolicy(): void
    {
        $resolver = new FallbackSelectorResolver(
            policy: SelectorResolutionPolicy::CONTEXT_FIRST,
        );

        self::assertSame('context', $resolver->resolve(
            'company',
            ['selector' => 'context'],
            ['selector' => 'payload'],
        ));
    }

    #[Test]
    public function contextOnlyIgnoresPayload(): void
    {
        $resolver = new FallbackSelectorResolver(
            defaultSelector: 'fallback',
            policy: SelectorResolutionPolicy::CONTEXT_ONLY,
        );

        self::assertSame('fallback', $resolver->resolve(
            'company',
            [],
            ['selector' => 'payload'],
        ));
    }

    #[Test]
    public function payloadOnlyIgnoresContext(): void
    {
        $resolver = new FallbackSelectorResolver(
            defaultSelector: 'fallback',
            policy: SelectorResolutionPolicy::PAYLOAD_ONLY,
        );

        self::assertSame('fallback', $resolver->resolve(
            'company',
            ['selector' => 'context'],
            [],
        ));
    }
}
