<?php

declare(strict_types=1);

namespace PhpSoftBox\Requisites\Tests\Validation\Form\Company;

use PhpSoftBox\Requisites\Validation\Form\Company\CompanyRequisitesAmFormValidation;
use PhpSoftBox\Requisites\Validation\Form\Company\CompanyRequisitesAzFormValidation;
use PhpSoftBox\Requisites\Validation\Form\Company\CompanyRequisitesByFormValidation;
use PhpSoftBox\Requisites\Validation\Form\Company\CompanyRequisitesGenericFormValidation;
use PhpSoftBox\Requisites\Validation\Form\Company\CompanyRequisitesKzFormValidation;
use PhpSoftBox\Requisites\Validation\Form\Company\CompanyRequisitesRuFormValidation;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function array_map;
use function ord;
use function str_pad;
use function str_split;
use function strlen;
use function strtolower;
use function strtoupper;
use function substr;

use const STR_PAD_LEFT;

#[CoversClass(CompanyRequisitesRuFormValidation::class)]
#[CoversClass(CompanyRequisitesGenericFormValidation::class)]
#[CoversClass(CompanyRequisitesKzFormValidation::class)]
#[CoversClass(CompanyRequisitesByFormValidation::class)]
#[CoversClass(CompanyRequisitesAmFormValidation::class)]
#[CoversClass(CompanyRequisitesAzFormValidation::class)]
final class CompanyRequisitesFormValidationTest extends TestCase
{
    #[Test]
    public function ruFormValidationNormalizesAndValidatesPayload(): void
    {
        $inn  = self::buildValidInn10('123456789');
        $ogrn = self::buildValidOgrn('102770013219');
        $bik  = '044525225';

        $form = new CompanyRequisitesRuFormValidation([
            'country_code'               => ' ru ',
            'organization_type'          => ' LEGAL ',
            'organization_name'          => ' ООО Ромашка ',
            'organization_inn'           => $inn,
            'organization_kpp'           => '7701ab001',
            'organization_ogrn'          => $ogrn,
            'organization_address'       => ' Москва ',
            'bank_name'                  => ' Тестовый банк ',
            'bank_bik'                   => $bik,
            'bank_corr_account'          => self::buildValidAccountForBik($bik, 'correspondent', '3010181040000000000'),
            'bank_account_number'        => self::buildValidAccountForBik($bik, 'settlement', '4070281090000000000'),
            'accountant_same_as_manager' => '1',
        ]);

        $data = $form->validate();

        self::assertSame('RU', $data['country_code'] ?? null);
        self::assertSame('legal', $data['organization_type'] ?? null);
        self::assertSame('ООО Ромашка', $data['organization_name'] ?? null);
        self::assertSame('7701AB001', $data['organization_kpp'] ?? null);
        self::assertSame('Москва', $data['organization_address'] ?? null);
        self::assertTrue((bool) ($data['accountant_same_as_manager'] ?? false));
    }

    #[Test]
    public function genericFormValidationSupportsNonRuCountries(): void
    {
        $form = new CompanyRequisitesGenericFormValidation([
            'country_code'         => 'kz',
            'organization_type'    => ' individual ',
            'organization_name'    => ' ИП Тест ',
            'organization_inn'     => '123456789012',
            'organization_address' => ' Алматы ',
            'bank_name'            => ' Банк ',
            'bank_account_number'  => '1234567890',
        ]);

        $data = $form->validate();

        self::assertSame('KZ', $data['country_code'] ?? null);
        self::assertSame('individual', $data['organization_type'] ?? null);
        self::assertArrayNotHasKey('organization_kpp', $data);
        self::assertArrayNotHasKey('organization_ogrn', $data);
        self::assertArrayNotHasKey('bank_bik', $data);
        self::assertArrayNotHasKey('bank_corr_account', $data);
    }

    #[Test]
    public function kzFormValidationSupportsCountrySpecificRules(): void
    {
        $form = new CompanyRequisitesKzFormValidation([
            'country_code'         => 'kz',
            'organization_type'    => 'legal',
            'organization_name'    => 'ТОО Тест',
            'organization_inn'     => self::buildValidKzBinIin('960101300123'),
            'organization_address' => 'Алматы',
            'bank_name'            => 'Нацбанк',
            'bank_account_number'  => 'kz128490123456789012',
        ]);

        $data = $form->validate();

        self::assertSame('KZ', $data['country_code'] ?? null);
        self::assertSame('KZ128490123456789012', $data['bank_account_number'] ?? null);
        self::assertArrayNotHasKey('organization_kpp', $data);
        self::assertArrayNotHasKey('organization_ogrn', $data);
        self::assertArrayNotHasKey('bank_bik', $data);
        self::assertArrayNotHasKey('bank_corr_account', $data);
    }

    #[Test]
    public function byFormValidationSupportsCountrySpecificRules(): void
    {
        $form = new CompanyRequisitesByFormValidation([
            'country_code'         => 'by',
            'organization_type'    => 'legal',
            'organization_name'    => 'ООО Тест',
            'organization_inn'     => '123456789',
            'organization_address' => 'Минск',
            'bank_name'            => 'Банк',
            'bank_account_number'  => 'by13nbrb3600900000002z00ab00',
        ]);

        $data = $form->validate();

        self::assertSame('BY', $data['country_code'] ?? null);
        self::assertSame('BY13NBRB3600900000002Z00AB00', $data['bank_account_number'] ?? null);
        self::assertArrayNotHasKey('organization_kpp', $data);
        self::assertArrayNotHasKey('organization_ogrn', $data);
        self::assertArrayNotHasKey('bank_bik', $data);
        self::assertArrayNotHasKey('bank_corr_account', $data);
    }

    #[Test]
    public function amFormValidationSupportsCountrySelector(): void
    {
        $form = new CompanyRequisitesAmFormValidation([
            'country_code'         => 'am',
            'organization_type'    => 'legal',
            'organization_name'    => 'ООО Тест',
            'organization_inn'     => '12345678',
            'organization_address' => 'Ереван',
            'bank_name'            => 'Банк',
            'bank_account_number'  => '4070281090000000000',
        ]);

        $data = $form->validate();

        self::assertSame('AM', $data['country_code'] ?? null);
        self::assertArrayNotHasKey('organization_kpp', $data);
        self::assertArrayNotHasKey('organization_ogrn', $data);
        self::assertArrayNotHasKey('bank_bik', $data);
        self::assertArrayNotHasKey('bank_corr_account', $data);
    }

    #[Test]
    public function azFormValidationSupportsCountrySelector(): void
    {
        $azIban = self::buildIban('AZ', 'NABZ00000000137010001944');

        $form = new CompanyRequisitesAzFormValidation([
            'country_code'         => 'az',
            'organization_type'    => 'legal',
            'organization_name'    => 'ООО Тест',
            'organization_inn'     => '1234567890',
            'organization_address' => 'Баку',
            'bank_name'            => 'Банк',
            'bank_account_number'  => strtolower($azIban),
        ]);

        $data = $form->validate();

        self::assertSame('AZ', $data['country_code'] ?? null);
        self::assertSame($azIban, $data['bank_account_number'] ?? null);
        self::assertArrayNotHasKey('organization_kpp', $data);
        self::assertArrayNotHasKey('organization_ogrn', $data);
        self::assertArrayNotHasKey('bank_bik', $data);
        self::assertArrayNotHasKey('bank_corr_account', $data);
    }

    private static function buildValidInn10(string $firstNineDigits): string
    {
        $digits = array_map('intval', str_split($firstNineDigits));
        $coeffs = [2, 4, 10, 3, 5, 9, 4, 6, 8];
        $sum    = 0;
        foreach ($coeffs as $i => $coeff) {
            $sum += $digits[$i] * $coeff;
        }

        return $firstNineDigits . (string) (($sum % 11) % 10);
    }

    private static function buildValidOgrn(string $firstTwelveDigits): string
    {
        $check = self::modByInt($firstTwelveDigits, 11) % 10;

        return $firstTwelveDigits . (string) $check;
    }

    private static function modByInt(string $number, int $divisor): int
    {
        $mod    = 0;
        $length = strlen($number);
        for ($i = 0; $i < $length; $i++) {
            $mod = (($mod * 10) + (int) $number[$i]) % $divisor;
        }

        return $mod;
    }

    private static function buildValidKzBinIin(string $twelveDigits): string
    {
        $prefix = substr($twelveDigits, 0, 11);
        for ($probe = 0; $probe <= 9; $probe++) {
            $candidate = $prefix . (string) $probe;
            $digits    = array_map('intval', str_split($candidate));
            $check     = self::kzBinIinChecksum($digits, [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11]);
            if ($check === 10) {
                $check = self::kzBinIinChecksum($digits, [3, 4, 5, 6, 7, 8, 9, 10, 11, 1, 2]);
            }
            if ($check !== 10) {
                return $prefix . (string) $check;
            }
        }

        return $prefix . '0';
    }

    private static function buildIban(string $countryCode, string $bban): string
    {
        $country = strtoupper($countryCode);
        $input   = $bban . $country . '00';
        $numeric = '';

        $length = strlen($input);
        for ($index = 0; $index < $length; $index++) {
            $char = $input[$index];
            if ($char >= '0' && $char <= '9') {
                $numeric .= $char;
                continue;
            }
            $numeric .= (string) (ord($char) - 55);
        }

        $remainder = 0;
        $numLen    = strlen($numeric);
        for ($index = 0; $index < $numLen; $index++) {
            $remainder = (($remainder * 10) + (int) $numeric[$index]) % 97;
        }

        $check = 98 - $remainder;

        return $country . str_pad((string) $check, 2, '0', STR_PAD_LEFT) . $bban;
    }

    /**
     * @param list<int> $digits
     * @param list<int> $weights
     */
    private static function kzBinIinChecksum(array $digits, array $weights): int
    {
        $sum = 0;
        for ($index = 0; $index < 11; $index++) {
            $sum += $digits[$index] * $weights[$index];
        }

        return $sum % 11;
    }

    /**
     * @param 'settlement'|'correspondent' $mode
     */
    private static function buildValidAccountForBik(string $bik, string $mode, string $nineteenDigits): string
    {
        $prefix = $mode === 'correspondent'
            ? '0' . substr($bik, 4, 2)
            : substr($bik, -3);

        for ($digit = 0; $digit <= 9; $digit++) {
            $account = $nineteenDigits . (string) $digit;
            $control = $prefix . $account;
            $coeffs  = [7, 1, 3];
            $sum     = 0;
            for ($i = 0; $i < 23; $i++) {
                $sum += (((int) $control[$i]) * $coeffs[$i % 3]) % 10;
            }
            if ($sum % 10 === 0) {
                return $account;
            }
        }

        return $nineteenDigits . '0';
    }
}
