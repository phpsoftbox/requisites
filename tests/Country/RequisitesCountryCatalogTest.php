<?php

declare(strict_types=1);

namespace PhpSoftBox\Requisites\Tests\Country;

use PhpSoftBox\Requisites\Country\RequisitesCountryCatalog;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(RequisitesCountryCatalog::class)]
final class RequisitesCountryCatalogTest extends TestCase
{
    #[Test]
    public function countryCodesAndOptionsAreStable(): void
    {
        self::assertSame(['RU', 'KZ', 'AM', 'AZ', 'BY'], RequisitesCountryCatalog::countryCodes());
        self::assertSame([
            ['value' => 'RU', 'label' => 'Россия'],
            ['value' => 'KZ', 'label' => 'Казахстан'],
            ['value' => 'AM', 'label' => 'Армения'],
            ['value' => 'AZ', 'label' => 'Азербайджан'],
            ['value' => 'BY', 'label' => 'Беларусь'],
        ], RequisitesCountryCatalog::countryOptions());
    }

    #[Test]
    public function defaultAndNormalizationUseKnownCountryList(): void
    {
        self::assertSame('RU', RequisitesCountryCatalog::defaultCountryCode());
        self::assertSame('KZ', RequisitesCountryCatalog::normalizeCountryCode('kz'));
        self::assertSame('RU', RequisitesCountryCatalog::normalizeCountryCode('unknown'));
    }
}
