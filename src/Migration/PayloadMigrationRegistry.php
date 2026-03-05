<?php

declare(strict_types=1);

namespace PhpSoftBox\Requisites\Migration;

use InvalidArgumentException;
use PhpSoftBox\Requisites\Contract\PayloadMigrationRegistryInterface;
use PhpSoftBox\Requisites\Contract\PayloadMigratorInterface;
use RuntimeException;

use function array_values;
use function count;
use function sprintf;

final readonly class PayloadMigrationRegistry implements PayloadMigrationRegistryInterface
{
    /**
     * @param list<PayloadMigratorInterface> $migrators
     */
    public function __construct(
        private array $migrators = [],
    ) {
    }

    public function chain(string $profile, string $selector, int $fromVersion, int $toVersion): array
    {
        if ($fromVersion < 1 || $toVersion < 1) {
            throw new InvalidArgumentException('Versions must be greater than or equal to 1.');
        }

        if ($fromVersion > $toVersion) {
            throw new InvalidArgumentException('From version must be less than or equal to target version.');
        }

        if ($fromVersion === $toVersion) {
            return [];
        }

        $chain = [];
        for ($stepFrom = $fromVersion; $stepFrom < $toVersion; $stepFrom++) {
            $stepTo  = $stepFrom + 1;
            $matched = [];
            foreach ($this->migrators as $migrator) {
                if ($migrator->supports($profile, $selector, $stepFrom, $stepTo)) {
                    $matched[] = $migrator;
                }
            }

            if (count($matched) !== 1) {
                throw new RuntimeException(sprintf(
                    'Invalid migrator configuration for %s/%s step %d -> %d. Matched: %d.',
                    $profile,
                    $selector,
                    $stepFrom,
                    $stepTo,
                    count($matched),
                ));
            }

            $chain[] = $matched[0];
        }

        return array_values($chain);
    }
}
