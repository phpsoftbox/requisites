<?php

declare(strict_types=1);

namespace PhpSoftBox\Requisites\Tests\Validation\Rule;

use PhpSoftBox\Requisites\Validation\Rule\Cn\UsccChecksumValidation;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function count;

#[CoversClass(UsccChecksumValidation::class)]
#[CoversMethod(UsccChecksumValidation::class, 'validate')]
final class UsccChecksumValidationTest extends TestCase
{
    /**
     * Проверим, что настоящие коды USCC проходят проверку контрольного символа.
     *
     * @see UsccChecksumValidation::validate()
     */
    #[Test]
    public function acceptsValidCodes(): void
    {
        $rule = new UsccChecksumValidation();

        self::assertSame([[], []], [$rule->validate('91230102MAKBAUE17B', 'inn', true, []), $rule->validate('91350100M000100Y43', 'inn', true, [])]);
    }

    /**
     * Проверим, что код с неверным контрольным символом или запрещённой буквой (O) отклоняется.
     *
     * @see UsccChecksumValidation::validate()
     */
    #[Test]
    public function rejectsWrongCheckCharacterAndForbiddenLetters(): void
    {
        $rule = new UsccChecksumValidation();

        self::assertSame(
            [1, 1],
            [count($rule->validate('91230102MAKBAUE17A', 'inn', true, [])), count($rule->validate('91230102MAKBAOE17B', 'inn', true, []))],
        );
    }
}
