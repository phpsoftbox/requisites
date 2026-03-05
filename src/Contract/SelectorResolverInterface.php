<?php

declare(strict_types=1);

namespace PhpSoftBox\Requisites\Contract;

interface SelectorResolverInterface
{
    /**
     * @param array<string, mixed> $context
     * @param array<string, mixed> $payload
     */
    public function resolve(string $profile, array $context, array $payload): string;
}
