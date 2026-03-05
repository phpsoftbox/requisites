<?php

declare(strict_types=1);

namespace PhpSoftBox\Requisites\Migration;

use InvalidArgumentException;
use RuntimeException;

use function is_array;
use function is_int;
use function sprintf;

final readonly class StaticTargetVersionResolver implements MigrationTargetVersionResolverInterface
{
    /**
     * @param array<string, int|array<string, int>> $versions
     */
    public function __construct(
        private array $versions,
    ) {
    }

    public function targetVersion(string $profile, string $selector): int
    {
        $profileConfig = $this->versions[$profile] ?? null;
        if ($profileConfig === null) {
            throw new RuntimeException(sprintf('Target version config not found for profile "%s".', $profile));
        }

        if (is_int($profileConfig)) {
            return $this->assertVersion($profileConfig, $profile, $selector);
        }

        if (!is_array($profileConfig)) {
            throw new RuntimeException(sprintf('Target version config is invalid for profile "%s".', $profile));
        }

        $version = $profileConfig[$selector] ?? $profileConfig['default'] ?? null;
        if (!is_int($version)) {
            throw new RuntimeException(sprintf(
                'Target version not found for profile "%s" and selector "%s".',
                $profile,
                $selector,
            ));
        }

        return $this->assertVersion($version, $profile, $selector);
    }

    private function assertVersion(int $version, string $profile, string $selector): int
    {
        if ($version < 1) {
            throw new InvalidArgumentException(sprintf(
                'Target version must be >= 1 for profile "%s" and selector "%s".',
                $profile,
                $selector,
            ));
        }

        return $version;
    }
}
