<?php

declare(strict_types=1);

namespace PhpSoftBox\Requisites\Contract;

use PhpSoftBox\Requisites\DTO\RequisitesRecord;
use PhpSoftBox\Requisites\DTO\RequisitesSchema;
use PhpSoftBox\Requisites\DTO\RequisitesSubject;
use PhpSoftBox\Validator\ValidationResult;

interface RequisitesManagerInterface
{
    public function load(RequisitesSubject $subject, string $profile): RequisitesRecord;

    /**
     * @param array<string, mixed> $payload
     */
    public function validate(RequisitesRecord $record, array $payload): ValidationResult;

    /**
     * @param array<string, mixed> $payload
     */
    public function save(RequisitesRecord $record, array $payload): RequisitesRecord;

    public function schema(RequisitesRecord $record): RequisitesSchema;

    public function migrate(RequisitesRecord $record): RequisitesRecord;
}
