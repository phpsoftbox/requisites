<?php

declare(strict_types=1);

namespace PhpSoftBox\Requisites\Contract;

use PhpSoftBox\Requisites\DTO\RequisitesInsertResult;
use PhpSoftBox\Requisites\DTO\RequisitesRecord;

interface AtomicCreateStorageInterface extends StorageAdapterInterface
{
    /**
     * Inserts a transient record or returns the canonical row created concurrently.
     * It never updates an existing row.
     */
    public function insertOrFind(RequisitesRecord $record): RequisitesInsertResult;
}
