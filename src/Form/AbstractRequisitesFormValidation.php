<?php

declare(strict_types=1);

namespace PhpSoftBox\Requisites\Form;

use PhpSoftBox\Collection\Collection;
use PhpSoftBox\Validator\AbstractFormValidation;
use PhpSoftBox\Validator\Exception\ValidationException;
use PhpSoftBox\Validator\ValidationError;
use PhpSoftBox\Validator\ValidationOptions;
use PhpSoftBox\Validator\ValidationResult;
use PhpSoftBox\Validator\Validator;
use PhpSoftBox\Validator\ValidatorInterface;

abstract class AbstractRequisitesFormValidation extends AbstractFormValidation
{
    /**
     * @var array<string, list<string>>
     */
    private array $filterErrors = [];

    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        protected array $payload,
        private readonly ValidatorInterface $validator = new Validator(),
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function validate(?ValidationOptions $options = null): array
    {
        $result = $this->validationResult($options);
        if ($result->hasErrors()) {
            throw new ValidationException($result);
        }

        return $result->filteredData();
    }

    public function validationResult(?ValidationOptions $options = null): ValidationResult
    {
        $this->filterErrors = [];
        $this->beforeValidation();

        if ($this->filterErrors !== []) {
            $result = new ValidationResult($this->toValidationErrors($this->filterErrors), $this->payload);

            $this->setValidationResult($result);

            return $result;
        }

        $result = $this->validator->validate(
            $this->payload,
            $this->rules(),
            $this->messages(),
            $this->attributes(),
            $options,
            $this->payload,
        );

        $this->setValidationResult($result);

        return $result;
    }

    /**
     * @param array<string, callable(mixed): mixed|list<callable(mixed): mixed>> $filters
     */
    protected function applyFilters(array $filters): void
    {
        $result = $this->applyPayloadFilters($this->payload, $filters);

        $this->payload = $result->payload;
        if ($result->errors !== []) {
            $this->filterErrors = Collection::from($this->filterErrors)
                ->merge($result->errors, ['recursive' => true, 'list' => 'append'])
                ->all();
        }
    }

    /**
     * @param array<string, mixed> $payload
     */
    protected function replacePayload(array $payload): void
    {
        $this->payload = $payload;
    }

    /**
     * @param array<string, mixed> $patch
     */
    protected function mergePayload(array $patch): void
    {
        $this->payload = Collection::from($this->payload)
            ->merge($patch, ['recursive' => true])
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    protected function payload(): array
    {
        return $this->payload;
    }

    /**
     * @param array<string, list<string>> $errors
     * @return array<string, list<ValidationError>>
     */
    private function toValidationErrors(array $errors): array
    {
        $prepared = [];

        foreach ($errors as $field => $messages) {
            foreach ($messages as $message) {
                $prepared[$field] ??= [];
                $prepared[$field][] = new ValidationError($field, 'filter', $message);
            }
        }

        return $prepared;
    }
}
