<?php

declare(strict_types=1);

namespace PhpSoftBox\Requisites\DTO;

final readonly class RequisitesRecord
{
    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $attachments
     */
    public function __construct(
        public string $profile,
        public string $selector,
        public int $schemaVersion,
        public string $subjectType,
        public int|string $subjectId,
        public array $payload = [],
        public array $attachments = [],
        public int|string|null $id = null,
    ) {
    }
}
