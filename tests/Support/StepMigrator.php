<?php

declare(strict_types=1);

namespace PhpSoftBox\Requisites\Tests\Support;

use PhpSoftBox\Requisites\Contract\PayloadMigratorInterface;
use PhpSoftBox\Requisites\Migration\RequisitesMigrationContext;

final readonly class StepMigrator implements PayloadMigratorInterface
{
    public function __construct(
        private string $profile,
        private string $selector,
        private int $fromVersion,
        private int $toVersion,
        private string $stepName,
    ) {
    }

    public function supports(string $profile, string $selector, int $fromVersion, int $toVersion): bool
    {
        return $this->profile === $profile
            && $this->selector === $selector
            && $this->fromVersion === $fromVersion
            && $this->toVersion === $toVersion;
    }

    public function migrate(array $payload, RequisitesMigrationContext $context): array
    {
        $payload['steps'][]   = $this->stepName;
        $payload['context'][] = $context->fromVersion . '->' . $context->toVersion;

        return $payload;
    }
}
