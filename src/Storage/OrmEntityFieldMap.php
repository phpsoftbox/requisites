<?php

declare(strict_types=1);

namespace PhpSoftBox\Requisites\Storage;

use PhpSoftBox\Requisites\Exception\InvalidFieldMapException;

use function array_keys;
use function in_array;
use function is_string;
use function sprintf;

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

    /**
     * @param array<string, string|null> $map
     */
    public static function fromArray(array $map): self
    {
        $allowed = [
            'idProperty',
            'profileProperty',
            'selectorProperty',
            'schemaVersionProperty',
            'subjectTypeProperty',
            'subjectIdProperty',
            'payloadProperty',
            'attachmentsProperty',
            'createdAtProperty',
            'updatedAtProperty',
        ];

        foreach (array_keys($map) as $property) {
            if (!in_array($property, $allowed, true)) {
                throw new InvalidFieldMapException(sprintf('Unknown ORM requisites field map key: "%s".', $property));
            }

            $value    = $map[$property];
            $nullable = $property === 'createdAtProperty' || $property === 'updatedAtProperty';
            if ((!$nullable && (!is_string($value) || $value === ''))
                || ($nullable && $value !== null && (!is_string($value) || $value === ''))
            ) {
                throw new InvalidFieldMapException(sprintf('Invalid ORM requisites field map value for "%s".', $property));
            }
        }

        return new self(...$map);
    }
}
