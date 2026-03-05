<?php

declare(strict_types=1);

namespace PhpSoftBox\Requisites\Tests\Validation;

use PhpSoftBox\Requisites\Validation\Rule\Am\TinFormatValidation;
use PhpSoftBox\Requisites\Validation\Rule\Az\IbanFormatValidation as AzIbanFormatValidation;
use PhpSoftBox\Requisites\Validation\Rule\Az\VoenFormatValidation;
use PhpSoftBox\Requisites\Validation\Rule\By\IbanFormatValidation as ByIbanFormatValidation;
use PhpSoftBox\Requisites\Validation\Rule\By\UnpFormatValidation;
use PhpSoftBox\Requisites\Validation\Rule\Kz\BinIinChecksumValidation;
use PhpSoftBox\Requisites\Validation\Rule\Kz\IbanFormatValidation as KzIbanFormatValidation;
use PhpSoftBox\Requisites\Validation\Rule\Ru\BankAccountChecksumValidation;
use PhpSoftBox\Requisites\Validation\Rule\Ru\InnChecksumValidation;
use PhpSoftBox\Requisites\Validation\Rule\Ru\KppFormatValidation;
use PhpSoftBox\Requisites\Validation\Rule\Ru\OgrnByTypeValidation;
use PhpSoftBox\Requisites\Validation\Rule\Ru\SnilsChecksumValidation;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function array_map;
use function array_merge;
use function ord;
use function str_pad;
use function str_split;
use function strlen;
use function strtoupper;
use function substr;

use const STR_PAD_LEFT;

#[CoversClass(InnChecksumValidation::class)]
#[CoversClass(OgrnByTypeValidation::class)]
#[CoversClass(BankAccountChecksumValidation::class)]
#[CoversClass(SnilsChecksumValidation::class)]
#[CoversClass(KppFormatValidation::class)]
#[CoversClass(BinIinChecksumValidation::class)]
#[CoversClass(KzIbanFormatValidation::class)]
#[CoversClass(UnpFormatValidation::class)]
#[CoversClass(ByIbanFormatValidation::class)]
#[CoversClass(TinFormatValidation::class)]
#[CoversClass(VoenFormatValidation::class)]
#[CoversClass(AzIbanFormatValidation::class)]
final class ChecksumRulesTest extends TestCase
{
    /**
     * Проверяет: InnChecksumValidation валидирует ИНН юрлица и ИП по organization_type.
     */
    #[Test]
    public function innRuleValidatesByOrganizationType(): void
    {
        $rule = new InnChecksumValidation('organization_type');

        $validLegal = self::buildValidInn10('123456789');
        $validIp    = self::buildValidInn12('1234567890');

        $legalErrors = $rule->validate(
            $validLegal,
            'organization_inn',
            true,
            ['organization_type' => 'legal'],
        );
        $ipErrors = $rule->validate(
            $validIp,
            'organization_inn',
            true,
            ['organization_type' => 'individual'],
        );
        $invalidErrors = $rule->validate(
            '1234567891',
            'organization_inn',
            true,
            ['organization_type' => 'legal'],
        );

        $this->assertSame([], $legalErrors);
        $this->assertSame([], $ipErrors);
        $this->assertNotSame([], $invalidErrors);
    }

    /**
     * Проверяет: OgrnByTypeValidation принимает валидные ОГРН/ОГРНИП для соответствующего organization_type.
     */
    #[Test]
    public function ogrnRuleValidatesByOrganizationType(): void
    {
        $rule = new OgrnByTypeValidation('organization_type');

        $validOgrn   = self::buildValidOgrn('102770013219');
        $validOgrnip = self::buildValidOgrnip('30450011600013');

        $this->assertSame(
            [],
            $rule->validate(
                $validOgrn,
                'organization_ogrn',
                true,
                ['organization_type' => 'legal'],
            ),
        );
        $this->assertSame(
            [],
            $rule->validate(
                $validOgrnip,
                'organization_ogrn',
                true,
                ['organization_type' => 'individual'],
            ),
        );
    }

    /**
     * Проверяет: BankAccountChecksumValidation валидирует пары БИК+счет (расчетный и корреспондентский).
     */
    #[Test]
    public function settlementAccountRuleValidatesPair(): void
    {
        $settlementRule    = new BankAccountChecksumValidation('bank_bik', 'settlement');
        $correspondentRule = new BankAccountChecksumValidation('bank_bik', 'correspondent');
        $bik               = '044525225';
        $validSettlement   = self::buildValidAccountForBik($bik, 'settlement', '4070281090000000000');
        $validCorr         = self::buildValidAccountForBik($bik, 'correspondent', '3010181040000000000');
        $invalidSettlement = substr($validSettlement, 0, 19) . (string) ((((int) $validSettlement[19]) + 1) % 10);

        $this->assertSame(
            [],
            $settlementRule->validate(
                $validSettlement,
                'bank_account_number',
                true,
                ['bank_bik' => $bik],
            ),
        );
        $this->assertSame(
            [],
            $correspondentRule->validate(
                $validCorr,
                'bank_corr_account',
                true,
                ['bank_bik' => $bik],
            ),
        );
        $this->assertNotSame(
            [],
            $settlementRule->validate(
                $invalidSettlement,
                'bank_account_number',
                true,
                ['bank_bik' => $bik],
            ),
        );
    }

    /**
     * Проверяет: SnilsChecksumValidation валидирует формат и контрольную сумму СНИЛС.
     */
    #[Test]
    public function snilsRuleValidatesChecksumAndFormat(): void
    {
        $rule       = new SnilsChecksumValidation();
        $validSnils = self::buildValidSnils('112233445');
        $invalid    = substr($validSnils, 0, 9) . '00';

        $this->assertSame([], $rule->validate($validSnils, 'snils', true, []));
        $this->assertNotSame([], $rule->validate($invalid, 'snils', true, []));
        $this->assertNotSame([], $rule->validate('1122334459A', 'snils', true, []));
    }

    /**
     * Проверяет: KppFormatValidation принимает только формат КПП (4 цифры + 2 [0-9A-Z] + 3 цифры).
     */
    #[Test]
    public function kppRuleValidatesFormat(): void
    {
        $rule = new KppFormatValidation();

        $this->assertSame([], $rule->validate('770101001', 'organization_kpp', true, []));
        $this->assertSame([], $rule->validate('7701AB001', 'organization_kpp', true, []));
        $this->assertNotSame([], $rule->validate('7701ab001', 'organization_kpp', true, []));
        $this->assertNotSame([], $rule->validate('7701001', 'organization_kpp', true, []));
    }

    /**
     * Проверяет: BinIinChecksumValidation валидирует БИН/ИИН Казахстана.
     */
    #[Test]
    public function kzBinIinRuleValidatesChecksum(): void
    {
        $rule       = new BinIinChecksumValidation();
        $validBin   = self::buildValidKzBinIin('960101300123');
        $invalidBin = substr($validBin, 0, 11) . (string) ((((int) $validBin[11]) + 1) % 10);

        $this->assertSame([], $rule->validate($validBin, 'organization_inn', true, []));
        $this->assertNotSame([], $rule->validate($invalidBin, 'organization_inn', true, []));
        $this->assertNotSame([], $rule->validate('12345678901', 'organization_inn', true, []));
    }

    /**
     * Проверяет: IbanFormatValidation (KZ/BY) валидирует страновые форматы IBAN.
     */
    #[Test]
    public function ibanRulesValidateCountryFormats(): void
    {
        $kzRule = new KzIbanFormatValidation();
        $byRule = new ByIbanFormatValidation();

        $this->assertSame([], $kzRule->validate('KZ128490123456789012', 'bank_account_number', true, []));
        $this->assertNotSame([], $kzRule->validate('KZ12SHORT', 'bank_account_number', true, []));
        $this->assertSame([], $byRule->validate('BY13NBRB3600900000002Z00AB00', 'bank_account_number', true, []));
        $this->assertNotSame([], $byRule->validate('BY13SHORT', 'bank_account_number', true, []));
    }

    /**
     * Проверяет: UnpFormatValidation принимает только 9 цифр.
     */
    #[Test]
    public function byUnpRuleValidatesFormat(): void
    {
        $rule = new UnpFormatValidation();

        $this->assertSame([], $rule->validate('123456789', 'organization_inn', true, []));
        $this->assertNotSame([], $rule->validate('12345678', 'organization_inn', true, []));
        $this->assertNotSame([], $rule->validate('12345AB89', 'organization_inn', true, []));
    }

    #[Test]
    public function amTinRuleValidatesFormat(): void
    {
        $rule = new TinFormatValidation();

        $this->assertSame([], $rule->validate('12345678', 'organization_inn', true, []));
        $this->assertNotSame([], $rule->validate('1234567', 'organization_inn', true, []));
        $this->assertNotSame([], $rule->validate('1234AB78', 'organization_inn', true, []));
    }

    #[Test]
    public function azVoenRuleValidatesFormat(): void
    {
        $rule = new VoenFormatValidation();

        $this->assertSame([], $rule->validate('1234567890', 'organization_inn', true, []));
        $this->assertNotSame([], $rule->validate('123456789', 'organization_inn', true, []));
        $this->assertNotSame([], $rule->validate('12345A7890', 'organization_inn', true, []));
    }

    #[Test]
    public function azIbanRuleValidatesFormatAndChecksum(): void
    {
        $rule      = new AzIbanFormatValidation();
        $validIban = self::buildIban('AZ', 'NABZ00000000137010001944');
        $invalid   = substr($validIban, 0, 27) . (string) ((((int) $validIban[27]) + 1) % 10);

        $this->assertSame([], $rule->validate($validIban, 'bank_account_number', true, []));
        $this->assertNotSame([], $rule->validate($invalid, 'bank_account_number', true, []));
        $this->assertNotSame([], $rule->validate('AZ00SHORT', 'bank_account_number', true, []));
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

    private static function buildValidInn12(string $firstTenDigits): string
    {
        $digits  = array_map('intval', str_split($firstTenDigits));
        $coeff11 = [7, 2, 4, 10, 3, 5, 9, 4, 6, 8];
        $coeff12 = [3, 7, 2, 4, 10, 3, 5, 9, 4, 6, 8];
        $sum11   = 0;
        foreach ($coeff11 as $i => $coeff) {
            $sum11 += $digits[$i] * $coeff;
        }
        $check11 = ($sum11 % 11) % 10;

        $digitsWith11 = array_merge($digits, [$check11]);
        $sum12        = 0;
        foreach ($coeff12 as $i => $coeff) {
            $sum12 += $digitsWith11[$i] * $coeff;
        }
        $check12 = ($sum12 % 11) % 10;

        return $firstTenDigits . (string) $check11 . (string) $check12;
    }

    private static function buildValidOgrn(string $firstTwelveDigits): string
    {
        $check = self::modByInt($firstTwelveDigits, 11) % 10;

        return $firstTwelveDigits . (string) $check;
    }

    private static function buildValidOgrnip(string $firstFourteenDigits): string
    {
        $check = self::modByInt($firstFourteenDigits, 13) % 10;

        return $firstFourteenDigits . (string) $check;
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

    private static function buildValidSnils(string $firstNineDigits): string
    {
        $sum = 0;
        for ($index = 0; $index < 9; $index++) {
            $sum += ((int) $firstNineDigits[$index]) * (9 - $index);
        }

        $checkDigit = 0;
        if ($sum < 100) {
            $checkDigit = $sum;
        } elseif ($sum > 101) {
            $checkDigit = $sum % 101;
            if ($checkDigit === 100) {
                $checkDigit = 0;
            }
        }

        return $firstNineDigits . str_pad((string) $checkDigit, 2, '0', STR_PAD_LEFT);
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
