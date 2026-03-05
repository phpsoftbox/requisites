<?php

declare(strict_types=1);

namespace PhpSoftBox\Requisites\Storage;

use PhpSoftBox\Requisites\Contract\AtomicCreateStorageInterface;
use PhpSoftBox\Requisites\Contract\MigratableStorageInterface;
use PhpSoftBox\Requisites\Contract\StorageAdapterInterface;
use PhpSoftBox\Requisites\DTO\RequisitesInsertResult;
use PhpSoftBox\Requisites\DTO\RequisitesRecord;
use PhpSoftBox\Requisites\DTO\RequisitesSubject;
use PhpSoftBox\Requisites\Exception\ProfileNotFoundException;
use PhpSoftBox\Requisites\Exception\StorageException;
use PhpSoftBox\Requisites\Migration\MigrationStorageInfo;

use function is_string;
use function sprintf;

final readonly class ProfileRouterStorageAdapter implements MigratableStorageInterface
{
    /**
     * @param array<string, StorageAdapterInterface> $adaptersByProfile
     */
    public function __construct(
        private array $adaptersByProfile,
        private ?string $defaultProfile = null,
    ) {
    }

    public function find(RequisitesSubject $subject, string $profile): ?RequisitesRecord
    {
        return $this->adapterFor($profile)->find($subject, $profile);
    }

    public function create(RequisitesSubject $subject, string $profile): RequisitesRecord
    {
        return $this->adapterFor($profile)->create($subject, $profile);
    }

    public function save(RequisitesRecord $record): void
    {
        $this->adapterFor($record->profile)->save($record);
    }

    public function insertOrFind(RequisitesRecord $record): RequisitesInsertResult
    {
        $adapter = $this->adapterFor($record->profile);
        if (!$adapter instanceof AtomicCreateStorageInterface) {
            throw new StorageException(sprintf('Storage adapter for profile "%s" does not support atomic create.', $record->profile));
        }

        return $adapter->insertOrFind($record);
    }

    public function migrationBatch(
        string $profile,
        ?string $selector,
        int|string|null $afterId,
        int $limit,
    ): array {
        return $this->migratableAdapterFor($profile)->migrationBatch($profile, $selector, $afterId, $limit);
    }

    public function saveMigrated(RequisitesRecord $record, int $previousVersion): void
    {
        $this->migratableAdapterFor($record->profile)->saveMigrated($record, $previousVersion);
    }

    public function migrationStorageInfo(string $profile): MigrationStorageInfo
    {
        return $this->migratableAdapterFor($profile)->migrationStorageInfo($profile);
    }

    private function migratableAdapterFor(string $profile): MigratableStorageInterface
    {
        $adapter = $this->adapterFor($profile);
        if (!$adapter instanceof MigratableStorageInterface) {
            throw new StorageException(sprintf('Storage adapter for profile "%s" does not support backfill migrations.', $profile));
        }

        return $adapter;
    }

    private function adapterFor(string $profile): StorageAdapterInterface
    {
        $adapter = $this->adaptersByProfile[$profile] ?? null;
        if ($adapter instanceof StorageAdapterInterface) {
            return $adapter;
        }

        if (is_string($this->defaultProfile) && $this->defaultProfile !== '') {
            $defaultAdapter = $this->adaptersByProfile[$this->defaultProfile] ?? null;
            if ($defaultAdapter instanceof StorageAdapterInterface) {
                return $defaultAdapter;
            }
        }

        throw new ProfileNotFoundException(sprintf('Storage adapter for profile "%s" is not configured.', $profile));
    }
}
