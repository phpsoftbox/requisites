<?php

declare(strict_types=1);

namespace PhpSoftBox\Requisites\Tests\Support;

use PhpSoftBox\Requisites\Contract\PayloadMigratorInterface;
use PhpSoftBox\Requisites\Contract\RequisitesProfileInterface;
use PhpSoftBox\Requisites\DTO\RequisitesSchema;
use PhpSoftBox\Requisites\Profile\ProfileStorageDefinition;
use PhpSoftBox\Validator\FormValidationInterface;

final readonly class TestRequisitesProfile implements RequisitesProfileInterface
{
    /**
     * @param array<string, RequisitesSchema> $schemas
     * @param int|array<string, int> $targetVersions
     * @param array<string, class-string<FormValidationInterface>> $validators
     * @param list<PayloadMigratorInterface> $migrators
     */
    public function __construct(
        private string $name,
        private array $schemas,
        private int|array $targetVersions,
        private array $validators,
        private ProfileStorageDefinition $storage,
        private string $selectorKeyValue = 'selector',
        private string $defaultSelectorValue = 'default',
        private array $migrators = [],
    ) {
    }

    public function profile(): string
    {
        return $this->name;
    }

    public function selectorKey(): string
    {
        return $this->selectorKeyValue;
    }

    public function defaultSelector(): string
    {
        return $this->defaultSelectorValue;
    }

    public function schemas(): array
    {
        return $this->schemas;
    }

    public function targetVersions(): int|array
    {
        return $this->targetVersions;
    }

    public function formValidationClasses(): array
    {
        return $this->validators;
    }

    public function storageDefinition(): ProfileStorageDefinition
    {
        return $this->storage;
    }

    public function migrators(): array
    {
        return $this->migrators;
    }
}
