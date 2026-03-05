<?php

declare(strict_types=1);

namespace PhpSoftBox\Requisites\Validation\Rule\Az;

use PhpSoftBox\Validator\Rule\AbstractRule;
use PhpSoftBox\Validator\ValidationViolation;

use function ctype_digit;
use function is_string;
use function ord;
use function preg_match;
use function strlen;
use function strtoupper;
use function substr;

final class IbanFormatValidation extends AbstractRule
{
    public function validate(mixed $value, string $field, bool $present, array $data): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        if (!is_string($value)) {
            return [new ValidationViolation('iban_az_format')];
        }

        $iban = strtoupper($value);
        if (preg_match('/^AZ\d{2}[A-Z]{4}[A-Z0-9]{20}$/', $iban) !== 1) {
            return [new ValidationViolation('iban_az_format')];
        }

        return $this->isValidMod97($iban) ? [] : [new ValidationViolation('iban_az_format')];
    }

    public function messages(): array
    {
        return [
            'iban_az_format' => 'Поле {field} содержит некорректный IBAN Азербайджана.',
        ];
    }

    private function isValidMod97(string $iban): bool
    {
        $rearranged = substr($iban, 4) . substr($iban, 0, 4);
        $numeric    = '';
        $length     = strlen($rearranged);

        for ($index = 0; $index < $length; $index++) {
            $char = $rearranged[$index];
            if (ctype_digit($char)) {
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

        return $remainder === 1;
    }
}
