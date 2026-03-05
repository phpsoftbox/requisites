<?php

declare(strict_types=1);

namespace PhpSoftBox\Requisites\Schema;

use PhpSoftBox\Requisites\Contract\SelectorResolverInterface;

use function is_string;

final readonly class FallbackSelectorResolver implements SelectorResolverInterface
{
    public function __construct(
        private string $selectorKey = 'selector',
        private string $defaultSelector = 'default',
        private SelectorResolutionPolicy $policy = SelectorResolutionPolicy::PAYLOAD_FIRST,
    ) {
    }

    public function resolve(string $profile, array $context, array $payload): string
    {
        $selectorFromPayload = $this->selectorFrom($payload);
        $selectorFromContext = $context[$this->selectorKey] ?? null;
        $selectorFromContext = is_string($selectorFromContext) && $selectorFromContext !== ''
            ? $selectorFromContext
            : null;

        return match ($this->policy) {
            SelectorResolutionPolicy::PAYLOAD_FIRST => $selectorFromPayload ?? $selectorFromContext ?? $this->defaultSelector,
            SelectorResolutionPolicy::CONTEXT_FIRST => $selectorFromContext ?? $selectorFromPayload ?? $this->defaultSelector,
            SelectorResolutionPolicy::CONTEXT_ONLY  => $selectorFromContext ?? $this->defaultSelector,
            SelectorResolutionPolicy::PAYLOAD_ONLY  => $selectorFromPayload ?? $this->defaultSelector,
        };
    }

    /**
     * @param array<string, mixed> $source
     */
    private function selectorFrom(array $source): ?string
    {
        $selector = $source[$this->selectorKey] ?? null;

        return is_string($selector) && $selector !== '' ? $selector : null;
    }
}
