<?php

declare(strict_types=1);

namespace PhpSoftBox\Requisites\Schema;

use PhpSoftBox\Requisites\Contract\SchemaProviderInterface;
use PhpSoftBox\Requisites\DTO\RequisitesSchema;
use PhpSoftBox\Requisites\Exception\SchemaNotFoundException;

use function is_array;
use function sprintf;

final readonly class ArraySchemaProvider implements SchemaProviderInterface
{
    /**
     * @param array<string, array<string, RequisitesSchema>> $schemas
     */
    public function __construct(
        private array $schemas,
    ) {
    }

    public function schema(string $profile, string $selector): RequisitesSchema
    {
        $profileSchemas = $this->schemas[$profile] ?? null;
        if (!is_array($profileSchemas)) {
            throw new SchemaNotFoundException(sprintf('Schema profile not found: %s', $profile));
        }

        $schema = $profileSchemas[$selector] ?? $profileSchemas['default'] ?? null;
        if (!$schema instanceof RequisitesSchema) {
            throw new SchemaNotFoundException(sprintf(
                'Schema not found for profile "%s" and selector "%s".',
                $profile,
                $selector,
            ));
        }

        return $schema;
    }
}
