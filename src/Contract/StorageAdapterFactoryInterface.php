<?php

declare(strict_types=1);

namespace PhpSoftBox\Requisites\Contract;

use PhpSoftBox\Requisites\Profile\ProfileStorageDefinition;

interface StorageAdapterFactoryInterface
{
    public function create(ProfileStorageDefinition $definition): StorageAdapterInterface;
}
