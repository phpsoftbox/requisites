<?php

declare(strict_types=1);

namespace PhpSoftBox\Requisites\Profile;

use PhpSoftBox\Requisites\Contract\RequisitesProfileRegistryInterface;
use PhpSoftBox\Requisites\Schema\ArraySchemaProvider;

final readonly class ProfileSchemaProviderFactory
{
    public function create(RequisitesProfileRegistryInterface $registry): ArraySchemaProvider
    {
        $schemas  = [];
        $defaults = [];
        foreach ($registry->all() as $name => $profile) {
            $schemas[$name]  = $profile->schemas();
            $defaults[$name] = $profile->defaultSelector();
        }

        return new ArraySchemaProvider($schemas, $defaults);
    }
}
