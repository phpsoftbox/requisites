<?php

declare(strict_types=1);

namespace PhpSoftBox\Requisites\Attachment;

use PhpSoftBox\Requisites\Contract\AttachmentKeyPolicyInterface;
use Stringable;

use function array_key_exists;
use function is_array;
use function is_scalar;
use function is_string;
use function trim;

final readonly class AttachmentMapNormalizer
{
    public function __construct(
        private AttachmentKeyPolicyInterface $policy = new AllowAllAttachmentKeyPolicy(),
    ) {
    }

    /**
     * @param array<string, mixed> $attachments
     * @return array<string, string>
     */
    public function normalize(array $attachments, string $profile, string $selector): array
    {
        $result = [];
        foreach ($attachments as $rawKey => $rawValue) {
            $key = $this->normalizeKey($rawKey);
            if ($key === null || !$this->policy->isAllowed($profile, $selector, $key)) {
                continue;
            }

            $path = $this->normalizePath($rawValue);
            if ($path === null) {
                continue;
            }

            $result[$key] = $path;
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $current
     * @param array<string, mixed> $patch
     * @return array<string, string>
     */
    public function merge(array $current, array $patch, string $profile, string $selector): array
    {
        $normalized = $this->normalize($current, $profile, $selector);

        foreach ($patch as $rawKey => $rawValue) {
            $key = $this->normalizeKey($rawKey);
            if ($key === null || !$this->policy->isAllowed($profile, $selector, $key)) {
                continue;
            }

            // null/empty-string в patch означает удаление ключа.
            if ($rawValue === null || $rawValue === '') {
                unset($normalized[$key]);
                continue;
            }

            $path = $this->normalizePath($rawValue);
            if ($path === null) {
                continue;
            }

            $normalized[$key] = $path;
        }

        return $normalized;
    }

    private function normalizeKey(mixed $rawKey): ?string
    {
        if (!is_string($rawKey) && !is_scalar($rawKey)) {
            return null;
        }

        $key = trim((string) $rawKey);
        if ($key === '') {
            return null;
        }

        return $key;
    }

    private function normalizePath(mixed $rawValue): ?string
    {
        if (is_string($rawValue)) {
            $value = trim($rawValue);

            return $value !== '' ? $value : null;
        }

        if ($rawValue instanceof Stringable) {
            $value = trim((string) $rawValue);

            return $value !== '' ? $value : null;
        }

        if (is_array($rawValue) && array_key_exists('path', $rawValue)) {
            $path = $rawValue['path'];
            if (is_string($path)) {
                $value = trim($path);

                return $value !== '' ? $value : null;
            }
        }

        if (is_scalar($rawValue)) {
            $value = trim((string) $rawValue);

            return $value !== '' ? $value : null;
        }

        return null;
    }
}
