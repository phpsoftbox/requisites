<?php

declare(strict_types=1);

namespace PhpSoftBox\Requisites\Storage;

use InvalidArgumentException;
use PhpSoftBox\Requisites\Contract\StorageAdapterInterface;
use PhpSoftBox\Requisites\DTO\RequisitesRecord;
use PhpSoftBox\Requisites\DTO\RequisitesSubject;

use function is_string;
use function sprintf;

final readonly class ProfileRouterStorageAdapter implements StorageAdapterInterface
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

        throw new InvalidArgumentException(sprintf('Storage adapter for profile "%s" is not configured.', $profile));
    }
}
