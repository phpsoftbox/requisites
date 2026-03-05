<?php

declare(strict_types=1);

namespace PhpSoftBox\Requisites\Migration;

interface MigrationTargetVersionResolverInterface
{
    public function targetVersion(string $profile, string $selector): int;
}
