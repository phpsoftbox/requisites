<?php

declare(strict_types=1);

namespace PhpSoftBox\Requisites\Tests\Validation;

use PhpSoftBox\Database\Database;
use PhpSoftBox\Database\SchemaBuilder\TableBlueprint;
use PhpSoftBox\Requisites\Contract\RequisitesValidatorInterface;
use PhpSoftBox\Requisites\DTO\RequisitesRecord;
use PhpSoftBox\Requisites\DTO\RequisitesSubject;
use PhpSoftBox\Requisites\Storage\DefaultStorageAdapter;
use PhpSoftBox\Requisites\Tests\Support\IntegrationDatabases;
use PhpSoftBox\Requisites\Validation\Rule\Ru\InnChecksumValidation;
use PhpSoftBox\Validator\Rule\StringValidation;
use PhpSoftBox\Validator\ValidationResult;
use PhpSoftBox\Validator\Validator;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Throwable;

use function array_map;
use function str_split;

#[CoversNothing]
final class ValidateAndSaveIntegrationTest extends TestCase
{
    /**
     * Интеграционный тест: validate + save на SQLite (валидный payload сохраняется, невалидный нет).
     */
    #[Test]
    public function validateAndSaveFlowWorksOnSqlite(): void
    {
        try {
            $database = IntegrationDatabases::sqliteDatabase();
        } catch (Throwable $exception) {
            self::markTestSkipped($exception->getMessage());
        }

        self::runValidateAndSaveScenario($database, driver: 'sqlite');
    }

    /**
     * Интеграционный тест: validate + save на MariaDB.
     */
    #[Test]
    public function validateAndSaveFlowWorksOnMariadb(): void
    {
        try {
            $database = IntegrationDatabases::mariadbDatabase();
        } catch (Throwable $exception) {
            self::markTestSkipped($exception->getMessage());
        }

        self::runValidateAndSaveScenario($database, driver: 'mariadb');
    }

    /**
     * Интеграционный тест: validate + save на Postgres.
     */
    #[Test]
    public function validateAndSaveFlowWorksOnPostgres(): void
    {
        try {
            $database = IntegrationDatabases::postgresDatabase();
        } catch (Throwable $exception) {
            self::markTestSkipped($exception->getMessage());
        }

        self::runValidateAndSaveScenario($database, driver: 'postgres');
    }

    /**
     * @param 'sqlite'|'mariadb'|'postgres' $driver
     */
    private static function runValidateAndSaveScenario(Database $database, string $driver): void
    {
        self::prepareSchema($database, $driver);

        $adapter   = new DefaultStorageAdapter($database->manager());
        $validator = new class (new Validator()) implements RequisitesValidatorInterface {
            public function __construct(
                private readonly Validator
            $validator,
            ) {
            }

            public function validate(string $profile, string $selector, array $payload): ValidationResult
            {
                return $this->validator->validate(
                    $payload,
                    [
                        'org_type' => [
                            new StringValidation()->required(),
                        ],
                        'inn' => [
                            new StringValidation()->required(),
                            new InnChecksumValidation('org_type'),
                        ],
                        'kpp' => [
                            new StringValidation()->requiredIf(
                                static fn (array $data): bool => ($data['org_type'] ?? null) === 'legal',
                                'org_type=legal',
                            ),
                        ],
                    ],
                );
            }
        };

        try {
            $subjectValid = new RequisitesSubject('company', 201);
            $validPayload = [
                'org_type' => 'legal',
                'inn'      => self::buildValidInn10('123456789'),
                'kpp'      => '123456789',
            ];

            $validResult = $validator->validate('company', 'country:RU', $validPayload);
            self::assertFalse($validResult->hasErrors());

            $adapter->save(new RequisitesRecord(
                profile: 'company',
                selector: 'country:RU',
                schemaVersion: 1,
                subjectType: $subjectValid->type,
                subjectId: $subjectValid->id,
                payload: $validResult->filteredData(),
                attachments: [],
            ));

            self::assertNotNull($adapter->find($subjectValid, 'company'));

            $subjectInvalid = new RequisitesSubject('company', 202);
            $invalidPayload = [
                'org_type' => 'legal',
                'inn'      => '123',
            ];

            $invalidResult = $validator->validate('company', 'country:RU', $invalidPayload);
            self::assertTrue($invalidResult->hasErrors());
            self::assertNull($adapter->find($subjectInvalid, 'company'));
        } finally {
            $database->execute('DROP TABLE IF EXISTS requisites_records');
        }
    }

    /**
     * @param 'sqlite'|'mariadb'|'postgres' $driver
     */
    private static function prepareSchema(Database $database, string $driver): void
    {
        $database->execute('DROP TABLE IF EXISTS requisites_records');

        $database->connection()->schema()->create('requisites_records', static function (TableBlueprint $table) use ($driver): void {
            if ($driver === 'mariadb') {
                $table->engine('InnoDB');
            }

            $table->id();
            $table->string('subject_type', 120);
            $table->string('subject_id', 64);
            $table->string('profile', 80);
            $table->string('selector', 120)->default('default');
            $table->json('payload_json')->nullable();
            $table->json('attachments_json')->nullable();
            $table->integer('schema_version')->default(1);
            $table->datetime('created_datetime');
            $table->datetime('updated_datetime');
            $table->unique(['subject_type', 'subject_id', 'profile'], 'requisites_subject_profile_unique');
            $table->index(['profile', 'selector'], 'requisites_profile_selector_index');
        });
    }

    private static function buildValidInn10(string $firstNineDigits): string
    {
        $digits = array_map('intval', str_split($firstNineDigits));
        $coeffs = [2, 4, 10, 3, 5, 9, 4, 6, 8];
        $sum    = 0;
        foreach ($coeffs as $i => $coeff) {
            $sum += $digits[$i] * $coeff;
        }

        return $firstNineDigits . (string) (($sum % 11) % 10);
    }
}
