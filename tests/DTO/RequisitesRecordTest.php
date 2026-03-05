<?php

declare(strict_types=1);

namespace PhpSoftBox\Requisites\Tests\DTO;

use PhpSoftBox\Requisites\DTO\RequisitesRecord;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(RequisitesRecord::class)]
final class RequisitesRecordTest extends TestCase
{
    /**
     * Проверяет: record корректно хранит обязательные и опциональные поля.
     */
    #[Test]
    public function itStoresConstructorValues(): void
    {
        $record = new RequisitesRecord(
            profile: 'company',
            selector: 'country:RU',
            schemaVersion: 2,
            subjectType: 'company',
            subjectId: 15,
            payload: ['inn' => '1234567890'],
            attachments: ['director_signature' => '/files/sign.png'],
            id: 77,
        );

        $this->assertSame('company', $record->profile);
        $this->assertSame('country:RU', $record->selector);
        $this->assertSame(2, $record->schemaVersion);
        $this->assertSame('company', $record->subjectType);
        $this->assertSame(15, $record->subjectId);
        $this->assertSame(['inn' => '1234567890'], $record->payload);
        $this->assertSame(['director_signature' => '/files/sign.png'], $record->attachments);
        $this->assertSame(77, $record->id);
    }
}
