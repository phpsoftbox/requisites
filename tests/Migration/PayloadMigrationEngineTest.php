<?php

declare(strict_types=1);

namespace PhpSoftBox\Requisites\Tests\Migration;

use InvalidArgumentException;
use PhpSoftBox\Requisites\DTO\RequisitesRecord;
use PhpSoftBox\Requisites\Migration\PayloadMigrationEngine;
use PhpSoftBox\Requisites\Migration\PayloadMigrationRegistry;
use PhpSoftBox\Requisites\Tests\Support\StepMigrator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(PayloadMigrationEngine::class)]
final class PayloadMigrationEngineTest extends TestCase
{
    /**
     * Проверяет: engine выполняет миграцию пошагово и повышает schema_version до target.
     */
    #[Test]
    public function migratesRecordStepByStep(): void
    {
        $engine = new PayloadMigrationEngine(new PayloadMigrationRegistry([
            new StepMigrator('company', 'country:RU', 1, 2, 'a'),
            new StepMigrator('company', 'country:RU', 2, 3, 'b'),
        ]));

        $record = new RequisitesRecord(
            profile: 'company',
            selector: 'country:RU',
            schemaVersion: 1,
            subjectType: 'company',
            subjectId: 11,
            payload: ['inn' => '123'],
            attachments: ['seal' => '/files/seal.png'],
            id: 101,
        );

        $migrated = $engine->migrate($record, 3);

        self::assertSame(3, $migrated->schemaVersion);
        self::assertSame(['a', 'b'], $migrated->payload['steps'] ?? null);
        self::assertSame(['1->2', '2->3'], $migrated->payload['context'] ?? null);
        self::assertSame('/files/seal.png', $migrated->attachments['seal'] ?? null);
    }

    /**
     * Проверяет: повторный вызов на уже целевой версии не меняет запись.
     */
    #[Test]
    public function migrateIsIdempotentWhenAlreadyOnTargetVersion(): void
    {
        $engine = new PayloadMigrationEngine(new PayloadMigrationRegistry([
            new StepMigrator('company', 'country:RU', 1, 2, 'a'),
            new StepMigrator('company', 'country:RU', 2, 3, 'b'),
        ]));

        $record = new RequisitesRecord(
            profile: 'company',
            selector: 'country:RU',
            schemaVersion: 3,
            subjectType: 'company',
            subjectId: 11,
            payload: ['steps' => ['a', 'b']],
            attachments: [],
            id: 101,
        );

        $migrated = $engine->migrate($record, 3);

        self::assertSame($record, $migrated);
    }

    /**
     * Проверяет: некорректная target version отклоняется.
     */
    #[Test]
    public function invalidTargetVersionThrowsException(): void
    {
        $engine = new PayloadMigrationEngine(new PayloadMigrationRegistry([]));
        $record = new RequisitesRecord(
            profile: 'company',
            selector: 'default',
            schemaVersion: 1,
            subjectType: 'company',
            subjectId: 1,
            payload: [],
            attachments: [],
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Target version must be greater than or equal to 1.');

        $engine->migrate($record, 0);
    }
}
