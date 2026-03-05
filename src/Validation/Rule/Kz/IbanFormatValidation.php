<?php

declare(strict_types=1);

namespace PhpSoftBox\Requisites\Validation\Rule\Kz;

use PhpSoftBox\Validator\Rule\AbstractRule;
use PhpSoftBox\Validator\ValidationViolation;

use function is_string;
use function preg_match;

final class IbanFormatValidation extends AbstractRule
{
    public function validate(mixed $value, string $field, bool $present, array $data): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        if (!is_string($value) || preg_match('/^KZ\d{2}[A-Z0-9]{16}$/', $value) !== 1) {
            return [new ValidationViolation('iban_kz_format')];
        }

        return [];
    }

    public function messages(): array
    {
        return [
            'iban_kz_format' => 'Поле {field} содержит некорректный IBAN Казахстана.',
        ];
    }
}
