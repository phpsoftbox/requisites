<?php

declare(strict_types=1);

namespace PhpSoftBox\Requisites\Tests\Country;

use PhpSoftBox\Filter\Phone\Drivers\PhoneDriverEnum;
use PhpSoftBox\Requisites\Country\PhoneCountryCatalog;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(PhoneCountryCatalog::class)]
final class PhoneCountryCatalogTest extends TestCase
{
    #[Test]
    public function driverByCountryCodeReturnsMatchingPhoneDriver(): void
    {
        self::assertSame(PhoneDriverEnum::RU, PhoneCountryCatalog::driverByCountryCode('RU'));
        self::assertSame(PhoneDriverEnum::KZ, PhoneCountryCatalog::driverByCountryCode('kz'));
        self::assertSame(PhoneDriverEnum::AM, PhoneCountryCatalog::driverByCountryCode('AM'));
        self::assertSame(PhoneDriverEnum::RU, PhoneCountryCatalog::driverByCountryCode('unknown'));
    }

    #[Test]
    public function normalizePhoneByCountryCodeUsesCountrySpecificDriver(): void
    {
        self::assertSame('9991112233', PhoneCountryCatalog::normalizePhoneByCountryCode('RU', '+7 (999) 111-22-33'));
        self::assertSame('7010000002', PhoneCountryCatalog::normalizePhoneByCountryCode('KZ', '+7 (701) 000-00-02'));
        self::assertSame('77123456', PhoneCountryCatalog::normalizePhoneByCountryCode('AM', '+374 (77) 123-456'));
        self::assertSame('501234567', PhoneCountryCatalog::normalizePhoneByCountryCode('AZ', '+994 (50) 123-45-67'));
        self::assertSame('291234567', PhoneCountryCatalog::normalizePhoneByCountryCode('BY', '+375 (29) 123-45-67'));
    }

    #[Test]
    public function normalizeAnyPhoneReturnsFirstMatchingCountry(): void
    {
        self::assertSame('7010000002', PhoneCountryCatalog::normalizeAnyPhone('+7 (701) 000-00-02'));
        self::assertSame('9991112233', PhoneCountryCatalog::normalizeAnyPhone('+7 (999) 111-22-33'));
        self::assertNull(PhoneCountryCatalog::normalizeAnyPhone('invalid-phone'));
    }
}
