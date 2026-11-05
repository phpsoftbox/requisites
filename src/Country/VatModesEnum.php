<?php

declare(strict_types=1);

namespace PhpSoftBox\Requisites\Country;

use function array_map;

/**
 * Режим НДС организации в международных реквизитах: «Организация без НДС» или плательщик НДС.
 */
enum VatModesEnum: string
{
    case WithoutVat = 'without_vat';
    case WithVat    = 'with_vat';

    public function label(): string
    {
        return match ($this) {
            self::WithoutVat => 'Организация без НДС',
            self::WithVat    => 'Плательщик НДС',
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
