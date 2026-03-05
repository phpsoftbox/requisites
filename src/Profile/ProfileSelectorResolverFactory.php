<?php

declare(strict_types=1);

namespace PhpSoftBox\Requisites\Profile;

use PhpSoftBox\Requisites\Contract\RequisitesProfileRegistryInterface;
use PhpSoftBox\Requisites\Schema\FallbackSelectorResolver;
use PhpSoftBox\Requisites\Schema\ProfileSelectorResolver;
use PhpSoftBox\Requisites\Schema\SelectorResolutionPolicy;

final readonly class ProfileSelectorResolverFactory
{
    /**
     * @param array<string, SelectorResolutionPolicy> $policies
     */
    public function create(
        RequisitesProfileRegistryInterface $registry,
        array $policies = [],
    ): ProfileSelectorResolver {
        $resolvers = [];
        foreach ($registry->all() as $name => $profile) {
            $resolvers[$name] = new FallbackSelectorResolver(
                selectorKey: $profile->selectorKey(),
                defaultSelector: $profile->defaultSelector(),
                policy: $policies[$name] ?? SelectorResolutionPolicy::PAYLOAD_FIRST,
            );
        }

        return new ProfileSelectorResolver($resolvers);
    }
}
