<?php

declare(strict_types=1);

namespace PhpSoftBox\Requisites\Validation\Rule\Ru;

use PhpSoftBox\Validator\Rule\AbstractRule;
use PhpSoftBox\Validator\ValidationViolation;

use function is_string;
use function preg_match;

final class KppFormatValidation extends AbstractRule
{
    public function validate(mixed $value, string $field, bool $present, array $data): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        if (!is_string($value) || preg_match('/^[0-9]{4}[0-9A-Z]{2}[0-9]{3}$/', $value) !== 1) {
            return [new ValidationViolation('kpp_format')];
        }

        return [];
    }

    public function messages(): array
    {
        return [
            'kpp_format' => 'Поле {field} содержит некорректный КПП.',
        ];
    }
}
