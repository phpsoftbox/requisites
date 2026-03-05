<?php

declare(strict_types=1);

namespace PhpSoftBox\Requisites\Country;

use function array_keys;
use function array_values;
use function in_array;
use function strtoupper;
use function trim;

final class RequisitesCountryCatalog
{
    /**
     * @var array<string, array{label: string}>
     */
    private const array COUNTRIES = [
        'RU' => [
            'label' => 'Россия',
        ],
        'KZ' => [
            'label' => 'Казахстан',
        ],
        'AM' => [
            'label' => 'Армения',
        ],
        'AZ' => [
            'label' => 'Азербайджан',
        ],
        'BY' => [
            'label' => 'Беларусь',
        ],
    ];

    /**
     * @return list<string>
     */
    public static function countryCodes(): array
    {
        return array_values(array_keys(self::COUNTRIES));
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function countryOptions(): array
    {
        $options = [];
        foreach (self::COUNTRIES as $code => $country) {
            $options[] = [
                'value' => $code,
                'label' => $country['label'],
            ];
        }

        return $options;
    }

    public static function defaultCountryCode(): string
    {
        if (in_array('RU', self::countryCodes(), true)) {
            return 'RU';
        }

        $codes = self::countryCodes();

        return $codes[0] ?? 'RU';
    }

    public static function normalizeCountryCode(?string $countryCode): string
    {
        $normalized = strtoupper(trim((string) $countryCode));

        return isset(self::COUNTRIES[$normalized]) ? $normalized : self::defaultCountryCode();
    }
}
