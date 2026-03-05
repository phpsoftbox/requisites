<?php

declare(strict_types=1);

namespace PhpSoftBox\Requisites\Storage;

use PDOException;
use PhpSoftBox\Database\Exception\QueryException;
use Throwable;

use function is_array;
use function is_int;
use function is_string;
use function str_contains;
use function strtoupper;

final readonly class UniqueConstraintViolationDetector
{
    public function matches(QueryException $exception): bool
    {
        $pdoException = $this->pdoException($exception);
        if ($pdoException === null) {
            return false;
        }

        $errorInfo = $pdoException->errorInfo;
        $sqlState  = is_array($errorInfo) && is_string($errorInfo[0] ?? null)
            ? strtoupper($errorInfo[0])
            : strtoupper((string) $pdoException->getCode());
        $driverCode = is_array($errorInfo) ? ($errorInfo[1] ?? null) : null;
        $message    = strtoupper($pdoException->getMessage());

        if ($sqlState === '23505') {
            return true;
        }
        if ((is_int($driverCode) || is_string($driverCode)) && (string) $driverCode === '1062') {
            return true;
        }

        return ($sqlState === '23000' || (string) $driverCode === '19')
            && (str_contains($message, 'UNIQUE CONSTRAINT FAILED')
                || str_contains($message, 'IS NOT UNIQUE'));
    }

    private function pdoException(Throwable $exception): ?PDOException
    {
        $current = $exception;
        do {
            if ($current instanceof PDOException) {
                return $current;
            }
            $current = $current->getPrevious();
        } while ($current instanceof Throwable);

        return null;
    }
}
