<?php

declare(strict_types=1);

namespace PhpSoftBox\Requisites\Schema;

use PhpSoftBox\Forms\DTO\FormDefinition;
use PhpSoftBox\Forms\DTO\FormFieldDefinition;
use PhpSoftBox\Requisites\DTO\RequisitesSchema;

use function array_is_list;
use function count;
use function in_array;
use function is_array;
use function is_string;

final class SchemaFieldResolver
{
    /**
     * @param array<string, mixed> $payload
     */
    public function resolve(RequisitesSchema $schema, array $payload): RequisitesSchema
    {
        $resolvedFields = [];

        foreach ($schema->form->fields as $field) {
            if (!$field instanceof FormFieldDefinition) {
                continue;
            }

            if (!$this->matchConditions($field->visibleWhen, $payload)) {
                continue;
            }

            $required = $field->required;
            if ($field->requiredWhen !== []) {
                $required = $this->matchConditions($field->requiredWhen, $payload);
            }

            $resolvedFields[] = $required === $field->required
                ? $field
                : new FormFieldDefinition(
                    key: $field->key,
                    label: $field->label,
                    fieldType: $field->fieldType,
                    required: $required,
                    description: $field->description,
                    multiple: $field->multiple,
                    searchable: $field->searchable,
                    valueType: $field->valueType,
                    intervalType: $field->intervalType,
                    format: $field->format,
                    options: $field->options,
                    meta: $field->meta,
                    requiredWhen: $field->requiredWhen,
                    visibleWhen: $field->visibleWhen,
                    suggest: $field->suggest,
                    server: $field->server,
                );
        }

        return new RequisitesSchema(
            profile: $schema->profile,
            selector: $schema->selector,
            version: $schema->version,
            form: new FormDefinition(
                id: $schema->form->id,
                title: $schema->form->title,
                fields: $resolvedFields,
                meta: $schema->form->meta,
            ),
            meta: $schema->meta,
        );
    }

    /**
     * Supported formats:
     * - map: ['org_type' => 'individual', 'country' => 'RU']
     * - list: [
     *      ['field' => 'org_type', 'operator' => '=', 'value' => 'legal'],
     *      ['field' => 'country', 'operator' => 'in', 'value' => ['RU', 'KZ']],
     *   ]
     *
     * @param array<string, mixed> $payload
     */
    private function matchConditions(mixed $conditions, array $payload): bool
    {
        if ($conditions === null) {
            return true;
        }

        if (!is_array($conditions)) {
            return false;
        }

        if ($conditions === []) {
            return true;
        }

        if (!array_is_list($conditions)) {
            foreach ($conditions as $field => $expected) {
                if (($payload[$field] ?? null) !== $expected) {
                    return false;
                }
            }

            return true;
        }

        foreach ($conditions as $condition) {
            if (!is_array($condition)) {
                return false;
            }

            $field = $condition['field'] ?? null;
            if (!is_string($field) || $field === '') {
                return false;
            }

            $operator = $condition['operator'] ?? '=';
            $actual   = $payload[$field] ?? null;
            $expected = $condition['value'] ?? null;

            if (!$this->matchCondition($actual, $operator, $expected)) {
                return false;
            }
        }

        return true;
    }

    private function matchCondition(mixed $actual, mixed $operator, mixed $expected): bool
    {
        if (!is_string($operator) || $operator === '' || $operator === '=') {
            return $actual === $expected;
        }

        if ($operator === '!=') {
            return $actual !== $expected;
        }

        if ($operator === 'in') {
            if (!is_array($expected) || count($expected) === 0) {
                return false;
            }

            return in_array($actual, $expected, true);
        }

        if ($operator === 'not_in') {
            if (!is_array($expected) || count($expected) === 0) {
                return false;
            }

            return !in_array($actual, $expected, true);
        }

        return false;
    }
}
