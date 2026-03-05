<?php

declare(strict_types=1);

namespace PhpSoftBox\Requisites\Contract;

use PhpSoftBox\Requisites\DTO\RequisitesRecord;
use PhpSoftBox\Requisites\DTO\RequisitesSubject;

interface StorageAdapterInterface
{
    public function find(RequisitesSubject $subject, string $profile): ?RequisitesRecord;

    public function create(RequisitesSubject $subject, string $profile): RequisitesRecord;

    public function save(RequisitesRecord $record): void;
}
