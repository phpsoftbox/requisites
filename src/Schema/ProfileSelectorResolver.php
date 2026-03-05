<?php

declare(strict_types=1);

namespace PhpSoftBox\Requisites\Schema;

use PhpSoftBox\Requisites\Contract\SelectorResolverInterface;
use PhpSoftBox\Requisites\Exception\ProfileNotFoundException;

use function sprintf;

final readonly class ProfileSelectorResolver implements SelectorResolverInterface
{
    /**
     * @param array<string, SelectorResolverInterface> $resolvers
     */
    public function __construct(
        private array $resolvers,
    ) {
    }

    public function resolve(string $profile, array $context, array $payload): string
    {
        $resolver = $this->resolvers[$profile] ?? null;
        if (!$resolver instanceof SelectorResolverInterface) {
            throw new ProfileNotFoundException(sprintf('Selector resolver for profile "%s" is not configured.', $profile));
        }

        return $resolver->resolve($profile, $context, $payload);
    }
}
