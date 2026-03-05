<?php

declare(strict_types=1);

namespace PhpSoftBox\Requisites\Country;

use PhpSoftBox\Filter\Phone\Drivers\PhoneDriverEnum;
use PhpSoftBox\Filter\PhoneFilter;

use function strtolower;

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
}
