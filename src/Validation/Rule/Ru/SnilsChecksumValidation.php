<?php

declare(strict_types=1);

namespace PhpSoftBox\Requisites\Validation\Rule\Ru;

use PhpSoftBox\Validator\Rule\AbstractRule;
use PhpSoftBox\Validator\ValidationViolation;

use function is_string;
use function preg_match;
use function substr;

final class SnilsChecksumValidation extends AbstractRule
{
    public function validate(mixed $value, string $field, bool $present, array $data): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        if (!is_string($value) || preg_match('/^\d{11}$/', $value) !== 1) {
            return [new ValidationViolation('snils_checksum')];
        }

        $sum = 0;
        for ($index = 0; $index < 9; $index++) {
            $sum += ((int) $value[$index]) * (9 - $index);
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

        $actual = (int) substr($value, -2);

        return $checkDigit === $actual ? [] : [new ValidationViolation('snils_checksum')];
    }

    public function messages(): array
    {
        return [
            'snils_checksum' => 'Поле {field} содержит некорректный СНИЛС.',
        ];
    }
}
