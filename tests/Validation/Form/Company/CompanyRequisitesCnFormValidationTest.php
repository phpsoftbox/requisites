<?php

declare(strict_types=1);

namespace PhpSoftBox\Requisites\Tests\Validation\Form\Company;

use PhpSoftBox\Requisites\Validation\Form\Company\CompanyRequisitesCnFormValidation;
use PhpSoftBox\Requisites\Validation\Form\Company\CompanyRequisitesInternationalFormValidation;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(CompanyRequisitesCnFormValidation::class)]
#[CoversClass(CompanyRequisitesInternationalFormValidation::class)]
#[CoversMethod(CompanyRequisitesCnFormValidation::class, 'rulesDefinition')]
#[CoversMethod(CompanyRequisitesInternationalFormValidation::class, 'filtersDefinition')]
final class CompanyRequisitesCnFormValidationTest extends TestCase
{
    /**
     * Проверим, что реквизиты китайской компании из образца проходят проверку: английские значения в общих ключах,
     * китайские — в `*_local`, регистрационный номер приводится к верхнему регистру.
     *
     * @see CompanyRequisitesCnFormValidation::rulesDefinition()
     * @see CompanyRequisitesInternationalFormValidation::filtersDefinition()
     */
    #[Test]
    public function acceptsChineseCompanyFromSample(): void
    {
        $data = new CompanyRequisitesCnFormValidation(self::sample(['organization_inn' => ' 91230102makbaue17b ']))->validate();

        self::assertSame(
            ['CN', '91230102MAKBAUE17B', 'Heilongjiang Fenghua Trading Co., Ltd.', '黑龙江峰华贸易有限公司', 'without_vat'],
            [$data['country_code'], $data['organization_inn'], $data['organization_name'], $data['organization_name_local'], $data['vat_mode']],
        );
    }

    /**
     * Проверим, что значения на китайском и банк необязательны.
     *
     * @see CompanyRequisitesCnFormValidation::rulesDefinition()
     */
    #[Test]
    public function localValuesAndBankAreOptional(): void
    {
        $form = new CompanyRequisitesCnFormValidation(self::sample([
            'organization_name_local'    => null,
            'representative_name_local'  => null,
            'organization_address_local' => null,
        ]));

        self::assertFalse($form->validationResult()->hasErrors());
    }

    /**
     * Проверим, что регистрационный номер с неверным контрольным символом отклоняется.
     *
     * @see CompanyRequisitesCnFormValidation::rulesDefinition()
     */
    #[Test]
    public function rejectsUsccWithWrongCheckCharacter(): void
    {
        $result = new CompanyRequisitesCnFormValidation(self::sample(['organization_inn' => '91230102MAKBAUE17C']))->validationResult();

        self::assertArrayHasKey('organization_inn', $result->errors());
    }

    /**
     * Проверим, что английское название, представитель и адрес обязательны.
     *
     * @see CompanyRequisitesCnFormValidation::rulesDefinition()
     */
    #[Test]
    public function requiresEnglishValues(): void
    {
        $result = new CompanyRequisitesCnFormValidation(self::sample([
            'organization_name'    => '',
            'representative_name'  => '',
            'organization_address' => '',
        ]))->validationResult();

        self::assertSame(
            [true, true, true],
            [isset($result->errors()['organization_name']), isset($result->errors()['representative_name']), isset($result->errors()['organization_address'])],
        );
    }

    /**
     * Проверим, что международная схема без списка стран принимает любую страну, а SWIFT проверяется по формату.
     *
     * @see CompanyRequisitesInternationalFormValidation::rulesDefinition()
     */
    #[Test]
    public function internationalSchemaAcceptsAnyCountryAndChecksSwift(): void
    {
        $valid   = new CompanyRequisitesInternationalFormValidation(self::sample(['country_code' => 'vn', 'organization_inn' => '0101234567', 'bank_swift' => 'bfteVNVX']));
        $invalid = new CompanyRequisitesInternationalFormValidation(self::sample(['country_code' => 'VN', 'organization_inn' => '0101234567', 'bank_swift' => 'BAD']));

        self::assertSame([false, true], [$valid->validationResult()->hasErrors(), isset($invalid->validationResult()->errors()['bank_swift'])]);
    }

    /**
     * @param array<string, mixed> $overrides
     *
     * @return array<string, mixed>
     */
    private static function sample(array $overrides = []): array
    {
        return $overrides + [
            'country_code'               => 'cn',
            'organization_type'          => 'legal',
            'vat_mode'                   => 'without_vat',
            'organization_inn'           => '91230102MAKBAUE17B',
            'organization_name'          => 'Heilongjiang Fenghua Trading Co., Ltd.',
            'organization_name_local'    => '黑龙江峰华贸易有限公司',
            'representative_name'        => 'Gao Yuhua',
            'representative_name_local'  => '高雨华',
            'organization_address'       => 'Room 23, 5th Floor, No.1558-23 Jingjiang West Road, Daoli District, Harbin City, Heilongjiang Province, P.R.China',
            'organization_address_local' => '黑龙江省哈尔滨市道里区景江西路1558-23号5层23号',
        ];
    }
}
