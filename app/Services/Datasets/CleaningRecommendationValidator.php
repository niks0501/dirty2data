<?php

namespace App\Services\Datasets;

class CleaningRecommendationValidator
{
    private const array DESTRUCTIVE_OPERATIONS = [
        'remove_column',
        'filter_invalid',
        'numeric_range_filter',
        'remove_duplicates',
    ];

    private const array COLUMN_OPTIONAL_OPERATIONS = [
        'remove_duplicates',
        'merge_columns',
    ];

    private const array TARGET_TYPE_ALIASES = [
        'string' => 'text',
        'integer' => 'numeric',
        'int' => 'numeric',
        'float' => 'numeric',
        'double' => 'numeric',
        'real' => 'numeric',
        'bool' => 'boolean',
    ];

    private const array TEXT_FORMAT_ALIASES = [
        'lower' => 'lowercase',
        'upper' => 'uppercase',
        'title_case' => 'title',
        'titlecase' => 'title',
    ];

    /**
     * @param  array<string, mixed>  $payload
     * @param  list<string>  $headers
     * @return list<array<string, mixed>>
     */
    public function validate(array $payload, array $headers, string $source): array
    {
        $recommendations = $payload['recommendations'] ?? [];

        if (! is_array($recommendations)) {
            return [];
        }

        $valid = [];

        foreach ($recommendations as $index => $recommendation) {
            if (! is_array($recommendation)) {
                continue;
            }

            $steps = $this->validSteps($recommendation['suggested_steps'] ?? [], $headers);

            if ($steps === []) {
                continue;
            }

            $column = $recommendation['column'] ?? ($steps[0]['column'] ?? null);

            if (is_string($column) && $column !== '' && ! in_array($column, $headers, true)) {
                continue;
            }

            $valid[] = [
                'id' => (string) ($recommendation['id'] ?? 'rec_'.$source.'_'.$index),
                'column' => is_string($column) && $column !== '' ? $column : null,
                'issue' => (string) ($recommendation['issue'] ?? 'Potential data quality issue detected.'),
                'severity' => $this->oneOf((string) ($recommendation['severity'] ?? 'medium'), ['low', 'medium', 'high'], 'medium'),
                'confidence' => max(0, min(1, (float) ($recommendation['confidence'] ?? 0.5))),
                'risk' => $this->risk($recommendation, $steps),
                'source' => $source,
                'suggested_steps' => $steps,
                'before_examples' => $this->stringArray($recommendation['before_examples'] ?? []),
                'after_examples' => $this->stringArray($recommendation['after_examples'] ?? []),
                'reason' => (string) ($recommendation['reason'] ?? 'Recommended from dataset profile and sample values.'),
                'requires_user_confirmation' => true,
            ];
        }

        return $valid;
    }

    /**
     * @param  list<string>  $headers
     * @return list<array<string, mixed>>
     */
    private function validSteps(mixed $steps, array $headers): array
    {
        if (! is_array($steps)) {
            return [];
        }

        $valid = [];

        foreach ($steps as $step) {
            if (! is_array($step)) {
                continue;
            }

            $operation = (string) ($step['operation'] ?? '');

            if (! in_array($operation, DatasetCleaner::OPERATIONS, true)) {
                continue;
            }

            $parameters = $step['parameters'] ?? [];

            if (! is_array($parameters)) {
                $parameters = [];
            }

            $parameters = $this->normalizeParameters($operation, $parameters);

            $column = (string) ($step['column'] ?? ($parameters['column'] ?? ''));

            if (! in_array($operation, self::COLUMN_OPTIONAL_OPERATIONS, true) && $column === '') {
                continue;
            }

            if ($column !== '' && ! in_array($column, $headers, true)) {
                continue;
            }

            if ($operation === 'merge_columns' && ! $this->validMergeColumns($parameters, $headers)) {
                continue;
            }

            if ($operation === 'remove_pattern' && ! $this->validRegex((string) ($parameters['pattern'] ?? ''))) {
                continue;
            }

            $valid[] = [
                'operation' => $operation,
                'column' => $column,
                'parameters' => $parameters,
            ];
        }

        return $valid;
    }

    /**
     * @param  array<string, mixed>  $parameters
     * @return array<string, mixed>
     */
    private function normalizeParameters(string $operation, array $parameters): array
    {
        if (in_array($operation, ['convert_type', 'filter_invalid'], true)) {
            $targetType = (string) ($parameters['target_type'] ?? '');

            if ($targetType !== '' && isset(self::TARGET_TYPE_ALIASES[$targetType])) {
                $parameters['target_type'] = self::TARGET_TYPE_ALIASES[$targetType];
            }
        }

        if ($operation === 'standardize_text') {
            $textFormat = (string) ($parameters['text_format'] ?? '');

            if ($textFormat !== '' && isset(self::TEXT_FORMAT_ALIASES[$textFormat])) {
                $parameters['text_format'] = self::TEXT_FORMAT_ALIASES[$textFormat];
            }
        }

        return $parameters;
    }

    /**
     * @param  array<string, mixed>  $recommendation
     * @param  list<array<string, mixed>>  $steps
     */
    private function risk(array $recommendation, array $steps): string
    {
        foreach ($steps as $step) {
            if (in_array($step['operation'], self::DESTRUCTIVE_OPERATIONS, true)) {
                return 'high';
            }
        }

        return $this->oneOf((string) ($recommendation['risk'] ?? 'medium'), ['low', 'medium', 'high'], 'medium');
    }

    /**
     * @param  list<string>  $allowed
     */
    private function oneOf(string $value, array $allowed, string $fallback): string
    {
        return in_array($value, $allowed, true) ? $value : $fallback;
    }

    /**
     * @return list<string>
     */
    private function stringArray(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_map(fn (mixed $item): string => (string) $item, array_slice($value, 0, 5)));
    }

    private function validRegex(string $pattern): bool
    {
        if ($pattern === '') {
            return false;
        }

        return @preg_match('~'.str_replace('~', '\\~', $pattern).'~u', '') !== false;
    }

    /**
     * @param  array<string, mixed>  $parameters
     * @param  list<string>  $headers
     */
    private function validMergeColumns(array $parameters, array $headers): bool
    {
        $columns = $parameters['columns'] ?? [];

        if (is_string($columns)) {
            $columns = array_values(array_filter(array_map('trim', explode(',', $columns)), fn (string $column): bool => $column !== ''));
        }

        if (! is_array($columns) || $columns === []) {
            return false;
        }

        foreach ($columns as $column) {
            if (! is_string($column) || ! in_array($column, $headers, true)) {
                return false;
            }
        }

        return true;
    }
}
