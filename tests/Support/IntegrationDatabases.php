<?php

declare(strict_types=1);

namespace PhpSoftBox\Requisites\Tests\Support;

use PDO;
use PDOException;
use PhpSoftBox\Database\Database;
use RuntimeException;

use function extension_loaded;

final class IntegrationDatabases
{
    private const string MARIADB_DSN_URL  = 'mariadb://phpsoftbox:phpsoftbox@mariadb:3306/phpsoftbox';
    private const string POSTGRES_DSN_URL = 'postgres://phpsoftbox:phpsoftbox@postgres:5432/phpsoftbox';
    private const string SQLITE_DSN_URL   = 'sqlite:///:memory:';

    /**
     * @throws RuntimeException
     */
    public static function postgresDatabase(): Database
    {
        if (!extension_loaded('pdo_pgsql')) {
            throw new RuntimeException('pdo_pgsql extension is not available.');
        }

        return self::database(self::POSTGRES_DSN_URL, 'Failed to connect to Postgres database.');
    }

    /**
     * @throws RuntimeException
     */
    public static function mariadbDatabase(): Database
    {
        if (!extension_loaded('pdo_mysql')) {
            throw new RuntimeException('pdo_mysql extension is not available.');
        }

        return self::database(self::MARIADB_DSN_URL, 'Failed to connect to MariaDB database.');
    }

    /**
     * @throws RuntimeException
     */
    public static function sqliteDatabase(): Database
    {
        return self::database(self::SQLITE_DSN_URL, 'Failed to connect to Sqlite database.');
    }

    /**
     * @throws RuntimeException
     */
    private static function database(string $dsn, string $errorMessage): Database
    {
        $config = [
            'connections' => [
                'default' => 'main',
                'main'    => [
                    'dsn'     => $dsn,
                    'options' => [
                        PDO::ATTR_TIMEOUT => 2,
                    ],
                ],
            ],
        ];

        try {
            $database = Database::fromConfig($config);
            $database->fetchOne('SELECT 1');
        } catch (PDOException $exception) {
            throw new RuntimeException($errorMessage, 0, $exception);
        }

        return $database;
    }
}
