<?php

declare(strict_types=1);

namespace PhpSoftBox\Requisites\Tests\Storage;

use PDOException;
use PhpSoftBox\Database\Exception\QueryException;
use PhpSoftBox\Requisites\Storage\UniqueConstraintViolationDetector;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(UniqueConstraintViolationDetector::class)]
final class UniqueConstraintViolationDetectorTest extends TestCase
{
    /**
     * @param array{0: string, 1: int|string, 2: string} $errorInfo
     */
    #[Test]
    #[DataProvider('uniqueViolations')]
    public function detectsSupportedUniqueViolations(array $errorInfo): void
    {
        self::assertTrue(new UniqueConstraintViolationDetector()->matches(self::queryException($errorInfo)));
    }

    #[Test]
    public function rejectsForeignKeyViolation(): void
    {
        self::assertFalse(new UniqueConstraintViolationDetector()->matches(self::queryException([
            '23503',
            7,
            'Foreign key violation',
        ])));
    }

    /**
     * @return iterable<string, array{array{0: string, 1: int|string, 2: string}}>
     */
    public static function uniqueViolations(): iterable
    {
        yield 'PostgreSQL' => [['23505', 7, 'Unique violation']];
        yield 'MariaDB' => [['23000', 1062, 'Duplicate entry']];
        yield 'SQLite' => [['23000', 19, 'UNIQUE constraint failed: records.id']];
    }

    /** @param array{0: string, 1: int|string, 2: string} $errorInfo */
    private static function queryException(array $errorInfo): QueryException
    {
        $pdoException = new PDOException($errorInfo[2]);

        $pdoException->errorInfo = $errorInfo;

        return new QueryException($pdoException->getMessage(), 0, $pdoException);
    }
}
