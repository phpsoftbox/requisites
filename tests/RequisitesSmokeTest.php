<?php

declare(strict_types=1);

namespace PhpSoftBox\Requisites\Tests;

use PhpSoftBox\Requisites\Requisites;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Requisites::class)]
final class RequisitesSmokeTest extends TestCase
{
    /**
     * Проверяет: пакет автозагружается и базовый класс доступен.
     */
    #[Test]
    public function packageClassIsAutoloadable(): void
    {
        $component = new Requisites();

        $this->assertSame('requisites', $component->packageName());
    }
}
