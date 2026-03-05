<?php

declare(strict_types=1);

namespace PhpSoftBox\Requisites\Tests\Attachment;

use PhpSoftBox\Requisites\Attachment\AttachmentMapNormalizer;
use PhpSoftBox\Requisites\Contract\AttachmentKeyPolicyInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(AttachmentMapNormalizer::class)]
final class AttachmentMapNormalizerTest extends TestCase
{
    /**
     * Проверяет: normalizer очищает мусорные значения и оставляет валидные пути.
     */
    #[Test]
    public function normalizeCleansInvalidValues(): void
    {
        $normalizer = new AttachmentMapNormalizer();

        $result = $normalizer->normalize([
            'seal'               => ' /files/seal.png ',
            'director_signature' => ['path' => '/files/sign.png'],
            'empty'              => ' ',
            ''                   => '/files/skip.png',
            'invalid_array'      => ['foo' => 'bar'],
            'number'             => 123,
        ], 'company', 'country:RU');

        $this->assertSame([
            'seal'               => '/files/seal.png',
            'director_signature' => '/files/sign.png',
            'number'             => '123',
        ], $result);
    }

    /**
     * Проверяет: merge обновляет значения и удаляет ключи с null/empty в patch.
     */
    #[Test]
    public function mergeUpdatesAndRemovesKeys(): void
    {
        $normalizer = new AttachmentMapNormalizer();

        $result = $normalizer->merge(
            current: [
                'seal'      => '/files/seal-v1.png',
                'signature' => '/files/sign-v1.png',
            ],
            patch: [
                'seal'      => '/files/seal-v2.png',
                'signature' => null,
                'stamp'     => '/files/stamp.png',
            ],
            profile: 'company',
            selector: 'country:RU',
        );

        $this->assertSame([
            'seal'  => '/files/seal-v2.png',
            'stamp' => '/files/stamp.png',
        ], $result);
    }

    /**
     * Проверяет: policy может запретить конкретные attachment keys.
     */
    #[Test]
    public function policyCanBlockKeys(): void
    {
        $normalizer = new AttachmentMapNormalizer(new class () implements AttachmentKeyPolicyInterface {
            public function isAllowed(string $profile, string $selector, string $key): bool
            {
                return $key !== 'forbidden';
            }
        });

        $result = $normalizer->normalize([
            'forbidden' => '/files/private.png',
            'allowed'   => '/files/public.png',
        ], 'company', 'default');

        $this->assertSame([
            'allowed' => '/files/public.png',
        ], $result);
    }
}
