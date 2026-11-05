<?php

declare(strict_types=1);

namespace PhpSoftBox\Requisites\Validation\Rule\Cn;

use PhpSoftBox\Validator\Rule\AbstractRule;
use PhpSoftBox\Validator\ValidationViolation;

use function is_string;
use function preg_match;
use function strpos;

/**
 * Единый код социального кредита Китая (USCC, GB 32100-2015): 18 символов из 31-символьного алфавита (цифры и
 * латиница без I, O, Z, S, V), последний — контрольный: 31 − Σ(значение × вес) mod 31, веса — 3^i mod 31.
 */
final class UsccChecksumValidation extends AbstractRule
{
    private const string ALPHABET = '0123456789ABCDEFGHJKLMNPQRTUWXY';

    private const array WEIGHTS = [1, 3, 9, 27, 19, 26, 16, 17, 20, 29, 25, 13, 8, 24, 10, 30, 28];

    public function validate(mixed $value, string $field, bool $present, array $data): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        if (!is_string($value) || preg_match('/^[0-9ABCDEFGHJKLMNPQRTUWXY]{18}$/', $value) !== 1) {
            return [new ValidationViolation('uscc_checksum')];
        }

        $sum = 0;
        for ($index = 0; $index < 17; $index++) {
            $sum += (int) strpos(self::ALPHABET, $value[$index]) * self::WEIGHTS[$index];
        }
        $check = (31 - $sum % 31) % 31;

        return self::ALPHABET[$check] === $value[17] ? [] : [new ValidationViolation('uscc_checksum')];
    }

    public function messages(): array
    {
        return [
            'uscc_checksum' => 'Поле {field} содержит некорректный регистрационный номер (USCC).',
        ];
    }
}
