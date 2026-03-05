<?php

declare(strict_types=1);

namespace PhpSoftBox\Requisites\DTO;

use PhpSoftBox\Forms\DTO\FormDefinition;

final readonly class RequisitesSchema
{
    /**
     * @param array<string, mixed> $meta
     */
    public function __construct(
        public string $profile,
        public string $selector,
        public int $version,
        public FormDefinition $form,
        public array $meta = [],
    ) {
    }
}
