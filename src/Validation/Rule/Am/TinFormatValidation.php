<?php

declare(strict_types=1);

namespace PhpSoftBox\Requisites\Validation\Rule\Am;

use PhpSoftBox\Validator\Rule\AbstractRule;
use PhpSoftBox\Validator\ValidationViolation;

use function is_string;
use function preg_match;

final class TinFormatValidation extends AbstractRule
{
    public function validate(mixed $value, string $field, bool $present, array $data): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        if (!is_string($value) || preg_match('/^\d{8}$/', $value) !== 1) {
            return [new ValidationViolation('tin_am_format')];
        }

        return [];
    }

    public function messages(): array
    {
        return [
            'tin_am_format' => 'Поле {field} содержит некорректный налоговый номер Армении (8 цифр).',
        ];
    }
}
