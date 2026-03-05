<?php

declare(strict_types=1);

namespace PhpSoftBox\Requisites\Validation\Rule\Ru;

use PhpSoftBox\Validator\Rule\AbstractRule;
use PhpSoftBox\Validator\Support\DataPath;
use PhpSoftBox\Validator\ValidationViolation;

use function array_map;
use function is_string;
use function preg_match;
use function str_split;
use function strlen;

final class InnChecksumValidation extends AbstractRule
{
    public function __construct(
        private readonly ?string $organizationTypeField = 'organization_type',
        private readonly string $legalType = 'legal',
        private readonly string $individualType = 'individual',
    ) {
    }

    public function validate(mixed $value, string $field, bool $present, array $data): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        if (!is_string($value)) {
            return [new ValidationViolation('inn_checksum')];
        }

        if (preg_match('/^\d+$/', $value) !== 1) {
            return [new ValidationViolation('inn_checksum')];
        }

        $length = strlen($value);
        if ($length !== 10 && $length !== 12) {
            return [new ValidationViolation('inn_checksum')];
        }

        $organizationType = null;
        if ($this->organizationTypeField !== null && $this->organizationTypeField !== '') {
            $raw = DataPath::get($data, $this->organizationTypeField);
            if (is_string($raw) && $raw !== '') {
                $organizationType = $raw;
            }
        }

        if ($organizationType === $this->legalType) {
            return $this->isValidInn10($value) ? [] : [new ValidationViolation('inn_checksum')];
        }

        if ($organizationType === $this->individualType) {
            return $this->isValidInn12($value) ? [] : [new ValidationViolation('inn_checksum')];
        }

        return ($this->isValidInn10($value) || $this->isValidInn12($value))
            ? []
            : [new ValidationViolation('inn_checksum')];
    }

    public function messages(): array
    {
        return [
            'inn_checksum' => 'Поле {field} содержит некорректный ИНН.',
        ];
    }

    private function isValidInn10(string $inn): bool
    {
        if (strlen($inn) !== 10 || preg_match('/^\d{10}$/', $inn) !== 1) {
            return false;
        }

        $digits = array_map('intval', str_split($inn));
        $check  = $this->checksum($digits, [2, 4, 10, 3, 5, 9, 4, 6, 8]);

        return $check === $digits[9];
    }

    private function isValidInn12(string $inn): bool
    {
        if (strlen($inn) !== 12 || preg_match('/^\d{12}$/', $inn) !== 1) {
            return false;
        }

        $digits  = array_map('intval', str_split($inn));
        $check11 = $this->checksum($digits, [7, 2, 4, 10, 3, 5, 9, 4, 6, 8]);
        $check12 = $this->checksum($digits, [3, 7, 2, 4, 10, 3, 5, 9, 4, 6, 8]);

        return $check11 === $digits[10] && $check12 === $digits[11];
    }

    /**
     * @param list<int> $digits
     * @param list<int> $coeffs
     */
    private function checksum(array $digits, array $coeffs): int
    {
        $sum = 0;
        foreach ($coeffs as $index => $coeff) {
            $sum += $digits[$index] * $coeff;
        }

        return ($sum % 11) % 10;
    }
}
