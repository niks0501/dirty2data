<?php

namespace App\Services\Datasets;

class DatasetProfiler
{
    private const int MAX_DISTINCT_VALUES = 10;

    private const int MAX_SAMPLE_VALUES = 5;

    public function __construct(private readonly ?DatasetTypeDetector $typeDetector = null) {}

    /**
     * @param  list<array<string, mixed>>  $records
     * @param  list<string>  $headers
     * @return array<string, mixed>
     */
    public function profile(array $records, array $headers): array
    {
        return [
            'row_count' => count($records),
            'column_count' => count($headers),
            'duplicate_count' => $this->duplicateCount($records),
            'columns' => array_map(
                fn (string $header): array => $this->profileColumn($records, $header),
                $headers,
            ),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $records
     * @return array<string, mixed>
     */
    private function profileColumn(array $records, string $column): array
    {
        $values = array_map(fn (array $record): mixed => $record[$column] ?? null, $records);
        $nonBlank = array_values(array_filter($values, fn (mixed $value): bool => ! $this->detector()->isBlank($value)));
        $type = $this->detector()->detect($nonBlank);
        $missingCount = count($values) - count($nonBlank);
        $frequencies = $this->frequencies($nonBlank);
        $mostFrequent = $this->mostFrequent($frequencies);
        $profile = [
            'name' => $column,
            'type' => $type,
            'missing_count' => $missingCount,
            'missing_percentage' => count($values) === 0 ? 0 : round(($missingCount / count($values)) * 100, 2),
            'unique_count' => count($frequencies),
            'distinct_values' => $this->distinctValues($frequencies),
            'sample_values' => $this->sampleValues($nonBlank),
            'most_frequent' => $mostFrequent,
            'mode' => $mostFrequent['value'] ?? null,
        ];

        if ($type === 'numeric') {
            $numbers = array_map(fn (mixed $value): float => (float) $value, $nonBlank);
            sort($numbers);

            $profile['minimum'] = $numbers[0] ?? null;
            $profile['maximum'] = $numbers[array_key_last($numbers)] ?? null;
            $profile['average'] = $numbers === [] ? null : round(array_sum($numbers) / count($numbers), 4);
            $profile['median'] = $this->median($numbers);
            $profile['outliers_iqr'] = $this->detectOutliersIqr($numbers);
        }

        if ($type === 'date') {
            $timestamps = array_map(fn (mixed $value): int => strtotime((string) $value), $nonBlank);
            sort($timestamps);

            $profile['minimum'] = isset($timestamps[0]) ? date('Y-m-d', $timestamps[0]) : null;
            $profile['maximum'] = isset($timestamps[array_key_last($timestamps)]) ? date('Y-m-d', $timestamps[array_key_last($timestamps)]) : null;
        }

        return $profile;
    }

    /**
     * @param  list<array<string, mixed>>  $records
     */
    private function duplicateCount(array $records): int
    {
        $seen = [];
        $duplicates = 0;

        foreach ($records as $record) {
            ksort($record);
            $key = json_encode($record);

            if (isset($seen[$key])) {
                $duplicates++;
            }

            $seen[$key] = true;
        }

        return $duplicates;
    }

    /**
     * @param  list<mixed>  $values
     * @return array<string, int>
     */
    private function frequencies(array $values): array
    {
        $frequencies = [];

        foreach ($values as $value) {
            $key = is_bool($value) ? ($value ? 'true' : 'false') : (string) $value;
            $frequencies[$key] = ($frequencies[$key] ?? 0) + 1;
        }

        arsort($frequencies);

        return $frequencies;
    }

    /**
     * @param  array<string, int>  $frequencies
     * @return array{value: string, count: int}|null
     */
    private function mostFrequent(array $frequencies): ?array
    {
        if ($frequencies === []) {
            return null;
        }

        $value = array_key_first($frequencies);

        return [
            'value' => $value,
            'count' => $frequencies[$value],
        ];
    }

    /**
     * @param  array<string, int>  $frequencies
     * @return list<array{value: string, count: int}>
     */
    private function distinctValues(array $frequencies): array
    {
        return collect($frequencies)
            ->take(self::MAX_DISTINCT_VALUES)
            ->map(fn (int $count, string $value): array => [
                'value' => $value,
                'count' => $count,
            ])
            ->values()
            ->all();
    }

    /**
     * @param  list<mixed>  $values
     * @return list<string>
     */
    private function sampleValues(array $values): array
    {
        $samples = [];

        foreach ($values as $value) {
            $sample = $this->stringValue($value);

            if (in_array($sample, $samples, true)) {
                continue;
            }

            $samples[] = $sample;

            if (count($samples) === self::MAX_SAMPLE_VALUES) {
                break;
            }
        }

        return $samples;
    }

    /**
     * @param  list<float>  $numbers
     */
    private function median(array $numbers): ?float
    {
        $count = count($numbers);

        if ($count === 0) {
            return null;
        }

        $middle = intdiv($count, 2);

        if ($count % 2 === 1) {
            return $numbers[$middle];
        }

        return round(($numbers[$middle - 1] + $numbers[$middle]) / 2, 4);
    }

    private function stringValue(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        return (string) $value;
    }

    private function detector(): DatasetTypeDetector
    {
        return $this->typeDetector ?? new DatasetTypeDetector;
    }

    /**
     * @param  list<float>  $numbers
     * @return array{count: int, lower_bound: float, upper_bound: float, q1: float, q3: float} | null
     */
    private function detectOutliersIqr(array $numbers): ?array
    {
        $count = count($numbers);

        if ($count < 4) {
            return null;
        }

        $q1Index = (int) floor(0.25 * ($count - 1));
        $q3Index = (int) floor(0.75 * ($count - 1));
        $q1 = $numbers[$q1Index];
        $q3 = $numbers[$q3Index];
        $iqr = $q3 - $q1;

        if ($iqr <= 0) {
            return null;
        }

        $lower = $q1 - 1.5 * $iqr;
        $upper = $q3 + 1.5 * $iqr;

        $outlierCount = 0;

        foreach ($numbers as $number) {
            if ($number < $lower || $number > $upper) {
                $outlierCount++;
            }
        }

        return [
            'count' => $outlierCount,
            'lower_bound' => round($lower, 4),
            'upper_bound' => round($upper, 4),
            'q1' => round($q1, 4),
            'q3' => round($q3, 4),
        ];
    }
}
