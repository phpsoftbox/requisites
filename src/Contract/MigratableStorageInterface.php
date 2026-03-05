<?php

declare(strict_types=1);

namespace PhpSoftBox\Requisites\Contract;

use PhpSoftBox\Requisites\DTO\RequisitesRecord;
use PhpSoftBox\Requisites\Migration\MigrationStorageInfo;

interface MigratableStorageInterface extends AtomicCreateStorageInterface
{
    /**
     * @return list<RequisitesRecord>
     */
    public function migrationBatch(
        string $profile,
        ?string $selector,
        int|string|null $afterId,
        int $limit,
    ): array;

    public function saveMigrated(RequisitesRecord $record, int $previousVersion): void;

    public function migrationStorageInfo(string $profile): MigrationStorageInfo;
}
