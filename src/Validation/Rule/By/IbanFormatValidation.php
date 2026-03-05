<?php

declare(strict_types=1);

namespace PhpSoftBox\Requisites\Validation\Rule\By;

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

        if (!is_string($value) || preg_match('/^BY\d{2}[A-Z0-9]{24}$/', $value) !== 1) {
            return [new ValidationViolation('iban_by_format')];
        }

        return [];
    }

    public function messages(): array
    {
        return [
            'iban_by_format' => 'Поле {field} содержит некорректный IBAN Беларуси.',
        ];
    }
}
