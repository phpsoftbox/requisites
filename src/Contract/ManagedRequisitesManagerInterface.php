<?php

declare(strict_types=1);

namespace PhpSoftBox\Requisites\Contract;

use PhpSoftBox\Requisites\DTO\RequisitesRecord;
use PhpSoftBox\Requisites\DTO\RequisitesSaveResult;
use PhpSoftBox\Requisites\DTO\RequisitesSchema;
use PhpSoftBox\Requisites\DTO\RequisitesSubject;
use PhpSoftBox\Validator\ValidationResult;

interface ManagedRequisitesManagerInterface extends RequisitesManagerInterface
{
    /**
     * @param array<string, mixed> $context
     */
    public function load(
        RequisitesSubject $subject,
        string $profile,
        array $context = [],
    ): RequisitesRecord;

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $context
     */
    public function validate(
        RequisitesRecord $record,
        array $payload,
        array $context = [],
    ): ValidationResult;

    /**
     * Legacy convenience method. Prefer validateAndSave() when validation errors are expected.
     *
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $context
     */
    public function save(
        RequisitesRecord $record,
        array $payload,
        array $context = [],
    ): RequisitesRecord;

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $context
     */
    public function validateAndSave(
        RequisitesRecord $record,
        array $payload,
        array $context = [],
    ): RequisitesSaveResult;

    /**
     * @param array<string, mixed> $context
     */
    public function schema(
        RequisitesRecord $record,
        array $context = [],
    ): RequisitesSchema;
}
