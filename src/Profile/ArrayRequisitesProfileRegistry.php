<?php

declare(strict_types=1);

namespace PhpSoftBox\Requisites\Profile;

use PhpSoftBox\Requisites\Contract\RequisitesProfileInterface;
use PhpSoftBox\Requisites\Contract\RequisitesProfileRegistryInterface;
use PhpSoftBox\Requisites\DTO\RequisitesSchema;
use PhpSoftBox\Requisites\Exception\DuplicateProfileException;
use PhpSoftBox\Requisites\Exception\MissingProfileValidatorException;
use PhpSoftBox\Requisites\Exception\ProfileNotFoundException;
use PhpSoftBox\Requisites\Exception\RequisitesConfigurationException;
use PhpSoftBox\Validator\FormValidationInterface;

use function is_a;
use function is_int;
use function is_string;
use function sprintf;

final readonly class ArrayRequisitesProfileRegistry implements RequisitesProfileRegistryInterface
{
    /** @var array<string, RequisitesProfileInterface> */
    private array $profiles;

    /**
     * @param iterable<RequisitesProfileInterface> $profiles
     */
    public function __construct(iterable $profiles)
    {
        $registered = [];
        foreach ($profiles as $profile) {
            $name = $profile->profile();
            if ($name === '') {
                throw new RequisitesConfigurationException('Requisites profile name must not be empty.');
            }
            if (isset($registered[$name])) {
                throw new DuplicateProfileException(sprintf('Duplicate requisites profile: "%s".', $name));
            }

            $this->validate($profile);
            $registered[$name] = $profile;
        }

        $this->profiles = $registered;
    }

    public function has(string $profile): bool
    {
        return isset($this->profiles[$profile]);
    }

    public function get(string $profile): RequisitesProfileInterface
    {
        return $this->profiles[$profile]
            ?? throw new ProfileNotFoundException(sprintf('Requisites profile not found: "%s".', $profile));
    }

    public function all(): array
    {
        return $this->profiles;
    }

    private function validate(RequisitesProfileInterface $profile): void
    {
        $name            = $profile->profile();
        $defaultSelector = $profile->defaultSelector();
        if ($profile->selectorKey() === '') {
            throw new RequisitesConfigurationException(sprintf('Selector key must not be empty for profile "%s".', $name));
        }
        if ($defaultSelector === '') {
            throw new RequisitesConfigurationException(sprintf('Default selector must not be empty for profile "%s".', $name));
        }

        $schemas = $profile->schemas();
        if ($schemas === []) {
            throw new RequisitesConfigurationException(sprintf('At least one schema is required for profile "%s".', $name));
        }
        if (!isset($schemas[$defaultSelector]) && !isset($schemas['default'])) {
            throw new RequisitesConfigurationException(sprintf(
                'Default schema "%s" is not configured for profile "%s".',
                $defaultSelector,
                $name,
            ));
        }

        $validators = $profile->formValidationClasses();
        foreach ($schemas as $selector => $schema) {
            if (!is_string($selector) || $selector === '' || !$schema instanceof RequisitesSchema) {
                throw new RequisitesConfigurationException(sprintf('Invalid schema map for profile "%s".', $name));
            }
            if ($schema->profile !== $name || $schema->selector !== $selector) {
                throw new RequisitesConfigurationException(sprintf(
                    'Schema identity mismatch for profile "%s" and selector "%s".',
                    $name,
                    $selector,
                ));
            }

            $formClass = $validators[$selector] ?? $validators['default'] ?? null;
            if (!is_string($formClass) || $formClass === '' || !is_a($formClass, FormValidationInterface::class, true)) {
                throw new MissingProfileValidatorException(sprintf(
                    'Validator is not configured for profile "%s" and selector "%s".',
                    $name,
                    $selector,
                ));
            }
        }

        $versions = $profile->targetVersions();
        if (is_int($versions)) {
            if ($versions < 1) {
                throw new RequisitesConfigurationException(sprintf('Target version must be >= 1 for profile "%s".', $name));
            }

            return;
        }

        foreach ($schemas as $selector => $_schema) {
            $version = $versions[$selector] ?? $versions['default'] ?? null;
            if (!is_int($version) || $version < 1) {
                throw new RequisitesConfigurationException(sprintf(
                    'Target version is not configured for profile "%s" and selector "%s".',
                    $name,
                    $selector,
                ));
            }
        }
    }
}
