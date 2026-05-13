<?php

namespace App\Services\AI;

class LocalHeuristicCleaningRecommendationProvider implements CleaningRecommendationProvider
{
    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function recommend(array $context): array
    {
        $recommendations = [];
        $signals = $context['heuristic_signals']['columns'] ?? [];
        $duplicateRows = (int) ($context['heuristic_signals']['duplicate_rows'] ?? 0);

        if ($duplicateRows > 0) {
            $recommendations[] = [
                'id' => 'rec_remove_duplicate_rows',
                'column' => null,
                'issue' => "{$duplicateRows} duplicate rows were detected.",
                'severity' => 'medium',
                'confidence' => 0.9,
                'risk' => 'high',
                'suggested_steps' => [
                    ['operation' => 'remove_duplicates', 'column' => '', 'parameters' => []],
                ],
                'before_examples' => [],
                'after_examples' => [],
                'reason' => 'Duplicate rows can inflate counts, totals, and charts.',
                'requires_user_confirmation' => true,
            ];
        }

        foreach ($signals as $column => $columnSignals) {
            if (($columnSignals['has_bracket_refs'] ?? false) === true) {
                $recommendations[] = $this->recommendation(
                    'rec_'.str($column)->slug('_')->toString().'_bracket_refs',
                    $column,
                    'Values contain bracketed reference markers such as [1].',
                    'Remove bracketed citation markers before type conversion.',
                    [
                        ['operation' => 'remove_pattern', 'column' => $column, 'parameters' => ['pattern' => '\\[[^\\]]*\\]']],
                    ],
                    ['7[2]', 'Value [ref]'],
                    ['7', 'Value'],
                );
            }

            if (($columnSignals['has_currency'] ?? false) === true) {
                $recommendations[] = $this->recommendation(
                    'rec_'.str($column)->slug('_')->toString().'_currency_number',
                    $column,
                    'Currency symbols or thousands separators may prevent numeric analysis.',
                    'Extract the numeric value, then convert the column to numeric.',
                    [
                        ['operation' => 'extract_number', 'column' => $column, 'parameters' => []],
                        ['operation' => 'convert_type', 'column' => $column, 'parameters' => ['target_type' => 'numeric']],
                    ],
                    ['$780,000,000'],
                    ['780000000'],
                );
            }

            if (($columnSignals['has_list_values'] ?? false) === true) {
                $recommendations[] = $this->recommendation(
                    'rec_'.str($column)->slug('_')->toString().'_list_values',
                    $column,
                    'Some cells look like multiple values stored in one field.',
                    'Standardize list separators so categories are easier to read and compare.',
                    [
                        ['operation' => 'parse_list', 'column' => $column, 'parameters' => ['delimiter' => ';', 'output_delimiter' => ', ']],
                    ],
                    ['A; B; C'],
                    ['A, B, C'],
                    0.72,
                );
            }
        }

        return ['recommendations' => array_slice($recommendations, 0, 8)];
    }

    public function providerName(): string
    {
        return 'local_heuristic';
    }

    public function modelName(): string
    {
        return 'rule-based-fallback';
    }

    /**
     * @param  list<array<string, mixed>>  $steps
     * @param  list<string>  $before
     * @param  list<string>  $after
     * @return array<string, mixed>
     */
    private function recommendation(string $id, string $column, string $issue, string $reason, array $steps, array $before, array $after, float $confidence = 0.86): array
    {
        return [
            'id' => $id,
            'column' => $column,
            'issue' => $issue,
            'severity' => 'medium',
            'confidence' => $confidence,
            'risk' => 'low',
            'suggested_steps' => $steps,
            'before_examples' => $before,
            'after_examples' => $after,
            'reason' => $reason,
            'requires_user_confirmation' => true,
        ];
    }
}
