<?php

declare(strict_types=1);

namespace PhpSoftBox\Requisites\Contract;

interface RequisitesProfileRegistryInterface
{
    public function has(string $profile): bool;

    public function get(string $profile): RequisitesProfileInterface;

    /**
     * @return array<string, RequisitesProfileInterface>
     */
    public function all(): array;
}
