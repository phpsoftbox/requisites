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

final class OgrnByTypeValidation extends AbstractRule
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
            return [new ValidationViolation('ogrn_by_type')];
        }

        $organizationType = null;
        if ($this->organizationTypeField !== null && $this->organizationTypeField !== '') {
            $raw = DataPath::get($data, $this->organizationTypeField);
            if (is_string($raw) && $raw !== '') {
                $organizationType = $raw;
            }
        }

        if ($organizationType === $this->legalType) {
            return $this->isValidOgrn($value) ? [] : [new ValidationViolation('ogrn_by_type')];
        }

        if ($organizationType === $this->individualType) {
            return $this->isValidOgrnip($value) ? [] : [new ValidationViolation('ogrn_by_type')];
        }

        return ($this->isValidOgrn($value) || $this->isValidOgrnip($value))
            ? []
            : [new ValidationViolation('ogrn_by_type')];
    }

    public function messages(): array
    {
        return [
            'ogrn_by_type' => 'Поле {field} содержит некорректный ОГРН/ОГРНИП.',
        ];
    }

    private function isValidOgrn(string $ogrn): bool
    {
        if (strlen($ogrn) !== 13 || preg_match('/^\d{13}$/', $ogrn) !== 1) {
            return false;
        }

        $base   = substr($ogrn, 0, 12);
        $check  = (int) substr($ogrn, 12, 1);
        $actual = $this->modByInt($base, 11) % 10;

        return $actual === $check;
    }

    private function isValidOgrnip(string $ogrnip): bool
    {
        if (strlen($ogrnip) !== 15 || preg_match('/^\d{15}$/', $ogrnip) !== 1) {
            return false;
        }

        $base   = substr($ogrnip, 0, 14);
        $check  = (int) substr($ogrnip, 14, 1);
        $actual = $this->modByInt($base, 13) % 10;

        return $actual === $check;
    }

    private function modByInt(string $number, int $divisor): int
    {
        $mod    = 0;
        $length = strlen($number);
        for ($i = 0; $i < $length; $i++) {
            $mod = (($mod * 10) + (int) $number[$i]) % $divisor;
        }

        return $mod;
    }
}
