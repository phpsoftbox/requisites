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
    ) {
    }

    public function resolve(string $profile, array $context, array $payload): string
    {
        $selectorFromPayload = $payload[$this->selectorKey] ?? null;
        if (is_string($selectorFromPayload) && $selectorFromPayload !== '') {
            return $selectorFromPayload;
        }

        $selectorFromContext = $context[$this->selectorKey] ?? null;
        if (is_string($selectorFromContext) && $selectorFromContext !== '') {
            return $selectorFromContext;
        }

        return $this->defaultSelector;
    }
}
