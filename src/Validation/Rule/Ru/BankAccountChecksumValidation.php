<?php

declare(strict_types=1);

namespace PhpSoftBox\Requisites\Validation\Rule\Ru;

use PhpSoftBox\Validator\Rule\AbstractRule;
use PhpSoftBox\Validator\Support\DataPath;
use PhpSoftBox\Validator\ValidationViolation;

use function is_string;
use function preg_match;
use function strlen;
use function substr;

final class BankAccountChecksumValidation extends AbstractRule
{
    /**
     * @param 'settlement'|'correspondent' $mode
     */
    public function __construct(
        private readonly string $bikField = 'bank_bik',
        private readonly string $mode = 'settlement',
    ) {
    }

    public function validate(mixed $value, string $field, bool $present, array $data): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        if (!is_string($value)) {
            return [new ValidationViolation('bank_account_checksum')];
        }

        $bik = DataPath::get($data, $this->bikField);
        if (!is_string($bik) || $bik === '') {
            return [];
        }

        if (preg_match('/^\d{20}$/', $value) !== 1 || preg_match('/^\d{9}$/', $bik) !== 1) {
            return [new ValidationViolation('bank_account_checksum')];
        }

        $prefix = $this->mode === 'correspondent'
            ? '0' . substr($bik, 4, 2)
            : substr($bik, -3);

        $control = $prefix . $value;
        if (strlen($control) !== 23) {
            return [new ValidationViolation('bank_account_checksum')];
        }

        $coefficients = [7, 1, 3];
        $sum          = 0;
        for ($i = 0; $i < 23; $i++) {
            $sum += (((int) $control[$i]) * $coefficients[$i % 3]) % 10;
        }

        return $sum % 10 === 0 ? [] : [new ValidationViolation('bank_account_checksum')];
    }

    public function messages(): array
    {
        return [
            'bank_account_checksum' => 'Поле {field} содержит некорректную связку БИК и счета.',
        ];
    }
}
