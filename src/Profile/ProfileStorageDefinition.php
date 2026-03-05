<?php

declare(strict_types=1);

namespace PhpSoftBox\Requisites\Profile;

use InvalidArgumentException;

use function is_array;
use function is_string;

final readonly class ProfileStorageDefinition
{
    /**
     * @param array<string, string|null> $ormFieldMap
     */
    public function __construct(
        public string $driver,
        public string $connection = 'default',
        public bool $migrationAware = true,
        public ?string $table = null,
        public ?string $entityClass = null,
        public array $ormFieldMap = [],
    ) {
        if ($this->driver !== 'orm' && $this->driver !== 'default') {
            throw new InvalidArgumentException('Profile storage driver must be "orm" or "default".');
        }

        if ($this->driver === 'orm' && (!is_string($this->entityClass) || $this->entityClass === '')) {
            throw new InvalidArgumentException('ORM profile storage requires non-empty entityClass.');
        }

        if ($this->driver === 'default' && (!is_string($this->table) || $this->table === '')) {
            throw new InvalidArgumentException('Default profile storage requires non-empty table.');
        }

        if (!is_array($this->ormFieldMap)) {
            throw new InvalidArgumentException('ormFieldMap must be an array.');
        }
    }

    /**
     * @param array<string, string|null> $ormFieldMap
     */
    public static function orm(
        string $entityClass,
        string $connection = 'default',
        bool $migrationAware = true,
        array $ormFieldMap = [],
    ): self {
        return new self(
            driver: 'orm',
            connection: $connection,
            migrationAware: $migrationAware,
            entityClass: $entityClass,
            ormFieldMap: $ormFieldMap,
        );
    }

    public static function default(
        string $table = 'requisites_records',
        string $connection = 'default',
        bool $migrationAware = true,
    ): self {
        return new self(
            driver: 'default',
            connection: $connection,
            migrationAware: $migrationAware,
            table: $table,
        );
    }
}
