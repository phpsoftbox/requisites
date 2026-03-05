<?php

declare(strict_types=1);

namespace PhpSoftBox\Requisites\Country;

use function array_map;

enum OrganizationTypesEnum: string
{
    case Individual = 'individual';
    case Legal      = 'legal';

    public function label(): string
    {
        return match ($this) {
            self::Individual => 'ИП',
            self::Legal      => 'Юридическое лицо',
        };
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function dropdown(): array
    {
        return array_map(
            static fn (self $item): array => [
                'value' => $item->value,
                'label' => $item->label(),
            ],
            self::cases(),
        );
    }
}
