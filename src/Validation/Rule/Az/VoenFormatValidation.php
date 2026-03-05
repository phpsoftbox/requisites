<?php

declare(strict_types=1);

namespace PhpSoftBox\Requisites\Validation\Rule\Az;

use PhpSoftBox\Validator\Rule\AbstractRule;
use PhpSoftBox\Validator\ValidationViolation;

use function is_string;
use function preg_match;

final class VoenFormatValidation extends AbstractRule
{
    public function validate(mixed $value, string $field, bool $present, array $data): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        if (!is_string($value) || preg_match('/^\d{10}$/', $value) !== 1) {
            return [new ValidationViolation('voen_az_format')];
        }

        return [];
    }

    public function messages(): array
    {
        return [
            'voen_az_format' => 'Поле {field} содержит некорректный VÖEN Азербайджана (10 цифр).',
        ];
    }
}
