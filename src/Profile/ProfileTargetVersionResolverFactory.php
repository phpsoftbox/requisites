<?php

declare(strict_types=1);

namespace PhpSoftBox\Requisites\Profile;

use PhpSoftBox\Requisites\Contract\RequisitesProfileRegistryInterface;
use PhpSoftBox\Requisites\Migration\StaticTargetVersionResolver;

final readonly class ProfileTargetVersionResolverFactory
{
    public function create(RequisitesProfileRegistryInterface $registry): StaticTargetVersionResolver
    {
        $versions = [];
        foreach ($registry->all() as $name => $profile) {
            $versions[$name] = $profile->targetVersions();
        }

        return new StaticTargetVersionResolver($versions);
    }
}
