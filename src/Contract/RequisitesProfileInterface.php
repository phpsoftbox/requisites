<?php

declare(strict_types=1);

namespace PhpSoftBox\Requisites\Contract;

use PhpSoftBox\Requisites\DTO\RequisitesSchema;
use PhpSoftBox\Requisites\Profile\ProfileStorageDefinition;
use PhpSoftBox\Validator\FormValidationInterface;

interface RequisitesProfileInterface
{
    public function profile(): string;

    public function selectorKey(): string;

    public function defaultSelector(): string;

    /**
     * @return array<string, RequisitesSchema>
     */
    public function schemas(): array;

    /**
     * @return int|array<string, int>
     */
    public function targetVersions(): int|array;

    /**
     * @return array<string, class-string<FormValidationInterface>>
     */
    public function formValidationClasses(): array;

    public function storageDefinition(): ProfileStorageDefinition;

    /**
     * @return list<PayloadMigratorInterface>
     */
    public function migrators(): array;
}
