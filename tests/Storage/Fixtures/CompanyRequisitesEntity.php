<?php

declare(strict_types=1);

namespace PhpSoftBox\Requisites\Tests\Storage\Fixtures;

use PhpSoftBox\Orm\Metadata\Attributes\Column;
use PhpSoftBox\Orm\Metadata\Attributes\Entity;
use PhpSoftBox\Orm\Metadata\Attributes\Id;

#[Entity(table: 'company_requisites')]
final class CompanyRequisitesEntity
{
    #[Id]
    #[Column(type: 'integer')]
    public ?int $id = null;

    #[Column(name: 'subject_type', type: 'string')]
    public string $subjectType = '';

    #[Column(name: 'subject_id', type: 'string')]
    public string $subjectId = '';

    #[Column(type: 'string')]
    public string $profile = '';

    #[Column(name: 'country_code', type: 'string')]
    public string $countryCode = 'default';

    #[Column(name: 'schema_version', type: 'integer')]
    public int $schemaVersion = 1;

    /**
     * @var array<string, mixed>
     */
    #[Column(name: 'payload_json', type: 'json', nullable: true)]
    public array $payload = [];

    /**
     * @var array<string, mixed>
     */
    #[Column(name: 'attachments_json', type: 'json', nullable: true)]
    public array $attachments = [];

    #[Column(name: 'created_datetime', type: 'string', nullable: true)]
    public ?string $createdDatetime = null;

    #[Column(name: 'updated_datetime', type: 'string', nullable: true)]
    public ?string $updatedDatetime = null;
}
