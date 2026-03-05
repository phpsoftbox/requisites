<?php

declare(strict_types=1);

namespace PhpSoftBox\Requisites\Contract;

use PhpSoftBox\Requisites\DTO\RequisitesSchema;

interface SchemaProviderInterface
{
    public function schema(string $profile, string $selector): RequisitesSchema;
}
