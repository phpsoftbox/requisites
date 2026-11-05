<?php

declare(strict_types=1);

namespace PhpSoftBox\Requisites\Country;

use PhpSoftBox\Filter\Phone\Drivers\PhoneDriverEnum;
use PhpSoftBox\Filter\Phone\PhoneNumber;
use PhpSoftBox\Filter\PhoneFilter;

use function array_values;
use function strtolower;
use function strtoupper;
use function trim;

/**
 * Телефоны стран справочника {@see RequisitesCountryCatalog}.
 *
 * Хранение — E.164 ({@see self::toInternational()}): номер однозначен без знания страны. Методы национального формата
 * ({@see self::normalizePhoneByCountryCode()}, {@see self::normalizeAnyPhone()}) оставлены для совместимости.
 */
final class PhoneCountryCatalog
{
    public static function driverByCountryCode(string $countryCode): PhoneDriverEnum
    {
        $countryCode = RequisitesCountryCatalog::normalizeCountryCode($countryCode);

        return PhoneDriverEnum::tryFrom(strtolower($countryCode)) ?? PhoneDriverEnum::RU;
    }

    public static function normalizePhoneByCountryCode(string $countryCode, ?string $rawPhone): ?string
    {
        return (new PhoneFilter(self::driverByCountryCode($countryCode)))($rawPhone);
    }

    public static function normalizeAnyPhone(?string $rawPhone): ?string
    {
        foreach (RequisitesCountryCatalog::countryCodes() as $countryCode) {
            $normalized = self::normalizePhoneByCountryCode($countryCode, $rawPhone);
            if ($normalized !== null) {
                return $normalized;
            }
        }

        return null;
    }

    /**
     * Драйверы телефонов стран справочника.
     *
     * @return list<PhoneDriverEnum>
     */
    public static function drivers(): array
    {
        $drivers = [];
        foreach (RequisitesCountryCatalog::countryCodes() as $countryCode) {
            $driver = PhoneDriverEnum::tryFrom(strtolower($countryCode));
            if ($driver !== null) {
                $drivers[$driver->value] = $driver;
            }
        }

        return array_values($drivers);
    }

    /**
     * Номер в E.164 (+79991234567). $countryCode — выбранная страна для номера без кода; номер с кодом другой страны
     * справочника определяется по коду.
     */
    public static function toInternational(?string $countryCode, ?string $rawPhone, bool $mobileOnly = true): ?string
    {
        return self::parse($countryCode, $rawPhone, $mobileOnly)?->e164();
    }

    /**
     * Страна справочника по номеру (RU, KZ, CN…) или null, если номер не распознан.
     */
    public static function countryCodeByPhone(?string $phone): ?string
    {
        $parsed = self::parse(null, $phone, false);

        return $parsed !== null ? strtoupper($parsed->country->value) : null;
    }

    /**
     * Номер по маске его страны (+7 (999) 123-45-67, +86 138 1234 5678) или null, если номер не распознан.
     */
    public static function format(?string $phone): ?string
    {
        return self::parse(null, $phone, false)?->format();
    }

    private static function parse(?string $countryCode, ?string $rawPhone, bool $mobileOnly): ?PhoneNumber
    {
        $preferred = $countryCode !== null && trim($countryCode) !== '' ? self::driverByCountryCode($countryCode) : null;

        return PhoneNumber::parse($rawPhone, $preferred, self::drivers(), $mobileOnly);
    }
}
