<?php

declare(strict_types=1);

namespace PhpSoftBox\Requisites\Tests\Storage\Fixtures;

use PhpSoftBox\Orm\Metadata\Attributes\Column;
use PhpSoftBox\Orm\Metadata\Attributes\Entity;
use PhpSoftBox\Orm\Metadata\Attributes\Id;

#[Entity(table: 'custom_requisites')]
final class CustomRequisitesEntity
{
    #[Id]
    #[Column(type: 'string')]
    public ?string $uuid = null;

    #[Column(name: 'owner_type', type: 'string')]
    public string $ownerType = '';

    #[Column(name: 'domain_owner_id', type: 'string')]
    public string $domainOwnerId = '';

    #[Column(name: 'profile_name', type: 'string')]
    public string $profileName = '';

    #[Column(name: 'schema_key', type: 'string')]
    public string $schemaKey = 'default';

    #[Column(name: 'payload_version', type: 'integer')]
    public int $payloadVersion = 1;

    /** @var array<string, mixed> */
    #[Column(name: 'data_json', type: 'json', nullable: true)]
    public array $data = [];

    /** @var array<string, mixed> */
    #[Column(name: 'files_json', type: 'json', nullable: true)]
    public array $files = [];

    #[Column(name: 'created_at', type: 'string', nullable: true)]
    public ?string $createdAt = null;

    #[Column(name: 'updated_at', type: 'string', nullable: true)]
    public ?string $updatedAt = null;
}
