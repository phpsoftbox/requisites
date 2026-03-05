<?php

declare(strict_types=1);

namespace PhpSoftBox\Requisites\Storage;

final readonly class OrmEntityFieldMap
{
    public function __construct(
        public string $idProperty = 'id',
        public string $profileProperty = 'profile',
        public string $selectorProperty = 'selector',
        public string $schemaVersionProperty = 'schemaVersion',
        public string $subjectTypeProperty = 'subjectType',
        public string $subjectIdProperty = 'subjectId',
        public string $payloadProperty = 'payload',
        public string $attachmentsProperty = 'attachments',
        public ?string $createdAtProperty = 'createdDatetime',
        public ?string $updatedAtProperty = 'updatedDatetime',
    ) {
    }
}
