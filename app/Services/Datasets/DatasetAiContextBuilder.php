<?php

namespace App\Services\Datasets;

use App\Models\Dataset;

class DatasetAiContextBuilder
{
    /**
     * @return array<string, mixed>
     */
    public function build(Dataset $dataset): array
    {
        $records = array_slice($dataset->cleaned_records ?? [], 0, (int) config('ai.max_sample_rows', 30));
        $headers = $dataset->headers ?? [];
        $profile = $dataset->profile ?? [];

        return [
            'dataset' => [
                'name' => $dataset->original_name,
                'row_count' => $dataset->row_count,
                'column_count' => $dataset->column_count,
                'headers' => $headers,
            ],
            'profile' => $this->profileSummary($profile),
            'samples' => $this->sampleValues($headers, $records),
            'heuristic_signals' => $this->heuristicSignals($headers, $records, $profile),
        ];
    }

    /**
     * @param  array<string, mixed>  $profile
     * @return list<array<string, mixed>>
     */
    private function profileSummary(array $profile): array
    {
        return array_map(fn (array $column): array => [
            'name' => $column['name'] ?? null,
            'type' => $column['type'] ?? null,
            'missing_count' => $column['missing_count'] ?? 0,
            'missing_percentage' => $column['missing_percentage'] ?? 0,
            'unique_count' => $column['unique_count'] ?? 0,
            'sample_values' => array_slice($column['sample_values'] ?? [], 0, 5),
            'most_frequent' => $column['most_frequent'] ?? null,
        ], $profile['columns'] ?? []);
    }

    /**
     * @param  list<string>  $headers
     * @param  list<array<string, mixed>>  $records
     * @return array<string, list<mixed>>
     */
    private function sampleValues(array $headers, array $records): array
    {
        $limit = (int) config('ai.max_sample_values_per_column', 20);
        $samples = [];

        foreach ($headers as $header) {
            $values = [];

            foreach ($records as $record) {
                $value = $record[$header] ?? null;

                if ($value === null || $value === '' || in_array($value, $values, true)) {
                    continue;
                }

                $values[] = $value;

                if (count($values) >= $limit) {
                    break;
                }
            }

            $samples[$header] = $values;
        }

        return $samples;
    }

    /**
     * @param  list<string>  $headers
     * @param  list<array<string, mixed>>  $records
     * @param  array<string, mixed>  $profile
     * @return array<string, mixed>
     */
    private function heuristicSignals(array $headers, array $records, array $profile): array
    {
        $signals = [
            'duplicate_rows' => $profile['duplicate_count'] ?? 0,
            'columns' => [],
        ];

        foreach ($headers as $header) {
            $values = array_values(array_filter(array_map(fn (array $record): string => (string) ($record[$header] ?? ''), $records), fn (string $value): bool => trim($value) !== ''));

            $signals['columns'][$header] = [
                'has_bracket_refs' => $this->any($values, '/\[[^\]]+\]/'),
                'has_currency' => $this->any($values, '/[$€£]\s?\d/'),
                'has_percentage' => $this->any($values, '/\d+(?:\.\d+)?%/'),
                'has_year_range' => $this->any($values, '/\b\d{4}\s*[\-–—]\s*\d{4}\b/u'),
                'has_list_values' => $this->any($values, '/[;,|]/'),
            ];
        }

        return $signals;
    }

    /**
     * @param  list<string>  $values
     */
    private function any(array $values, string $pattern): bool
    {
        foreach ($values as $value) {
            if (preg_match($pattern, $value) === 1) {
                return true;
            }
        }

        return false;
    }
}
