<?php

declare(strict_types=1);

namespace PhpSoftBox\Requisites\Validation\Rule\Kz;

use PhpSoftBox\Validator\Rule\AbstractRule;
use PhpSoftBox\Validator\ValidationViolation;

use function array_map;
use function is_string;
use function preg_match;
use function str_split;

final class BinIinChecksumValidation extends AbstractRule
{
    public function validate(mixed $value, string $field, bool $present, array $data): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        if (!is_string($value) || preg_match('/^\d{12}$/', $value) !== 1) {
            return [new ValidationViolation('bin_iin_checksum')];
        }

        $digits = array_map('intval', str_split($value));
        $check  = $this->checksum($digits, [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11]);

        if ($check === 10) {
            $check = $this->checksum($digits, [3, 4, 5, 6, 7, 8, 9, 10, 11, 1, 2]);
        }

        if ($check === 10) {
            return [new ValidationViolation('bin_iin_checksum')];
        }

        return $check === $digits[11] ? [] : [new ValidationViolation('bin_iin_checksum')];
    }

    public function messages(): array
    {
        return [
            'bin_iin_checksum' => 'Поле {field} содержит некорректный БИН/ИИН.',
        ];
    }

    /**
     * @param list<int> $digits
     * @param list<int> $weights
     */
    private function checksum(array $digits, array $weights): int
    {
        $sum = 0;
        for ($index = 0; $index < 11; $index++) {
            $sum += $digits[$index] * $weights[$index];
        }

        return $sum % 11;
    }
}
