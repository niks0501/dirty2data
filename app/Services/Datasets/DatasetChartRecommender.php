<?php

namespace App\Services\Datasets;

use App\Models\Dataset;

class DatasetChartRecommender
{
    private const int PIE_UNIQUE_LIMIT = 6;

    public function __construct(private readonly DatasetTypeDetector $typeDetector) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function recommend(Dataset $dataset): array
    {
        $records = $dataset->cleaned_records ?? [];
        $headers = $dataset->headers ?? [];
        $profiles = $this->columnProfiles($records, $headers);
        $recommendations = [];

        foreach ($profiles as $profile) {
            if (in_array($profile['type'], ['text', 'boolean'], true)) {
                $recommendations[] = $this->categoricalRecommendation($profile);
            }

            if ($profile['type'] === 'numeric') {
                $recommendations[] = [
                    'type' => 'histogram',
                    'x_column' => $profile['name'],
                    'y_column' => null,
                    'title' => 'Distribution of '.$profile['name'],
                    'reason' => 'Numeric columns are best reviewed with a histogram to show their value distribution.',
                ];
            }

            if ($profile['type'] === 'date') {
                $recommendations[] = [
                    'type' => 'line',
                    'x_column' => $profile['name'],
                    'y_column' => null,
                    'title' => 'Records over '.$profile['name'],
                    'reason' => 'Date columns can show trends over time using a line chart.',
                ];
            }
        }

        foreach ($profiles as $xProfile) {
            foreach ($profiles as $yProfile) {
                if ($xProfile['name'] === $yProfile['name'] || $yProfile['type'] !== 'numeric') {
                    continue;
                }

                if (in_array($xProfile['type'], ['text', 'boolean'], true)) {
                    $recommendations[] = [
                        'type' => 'bar',
                        'x_column' => $xProfile['name'],
                        'y_column' => $yProfile['name'],
                        'title' => $yProfile['name'].' by '.$xProfile['name'],
                        'reason' => 'A category plus a numeric column works well as a bar chart.',
                    ];
                }

                if ($xProfile['type'] === 'date') {
                    $recommendations[] = [
                        'type' => 'line',
                        'x_column' => $xProfile['name'],
                        'y_column' => $yProfile['name'],
                        'title' => $yProfile['name'].' over '.$xProfile['name'],
                        'reason' => 'A date plus a numeric column works well as a trend line.',
                    ];
                }

                if ($xProfile['type'] === 'numeric') {
                    $recommendations[] = [
                        'type' => 'scatter',
                        'x_column' => $xProfile['name'],
                        'y_column' => $yProfile['name'],
                        'title' => $yProfile['name'].' vs '.$xProfile['name'],
                        'reason' => 'Two numeric columns can reveal relationships using a scatter plot.',
                    ];
                }
            }
        }

        return array_slice($recommendations, 0, 8);
    }

    /**
     * @param  array<string, mixed>  $profile
     * @return array<string, mixed>
     */
    private function categoricalRecommendation(array $profile): array
    {
        $isLowCardinality = $profile['unique_count'] > 0 && $profile['unique_count'] <= self::PIE_UNIQUE_LIMIT;

        return [
            'type' => $isLowCardinality ? 'pie' : 'bar',
            'x_column' => $profile['name'],
            'y_column' => null,
            'title' => ($isLowCardinality ? 'Share of ' : 'Counts by ').$profile['name'],
            'reason' => $isLowCardinality
                ? 'This column has only a few categories, so a pie chart can show each category share.'
                : 'This column has several categories, so a bar chart is easier to compare.',
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $records
     * @param  list<string>  $headers
     * @return list<array{name: string, type: string, unique_count: int}>
     */
    private function columnProfiles(array $records, array $headers): array
    {
        return array_map(function (string $header) use ($records): array {
            $values = array_map(fn (array $record): mixed => $record[$header] ?? null, $records);
            $type = $this->typeDetector->detect($values);
            $unique = [];

            foreach ($values as $value) {
                if ($this->typeDetector->isBlank($value)) {
                    continue;
                }

                $unique[(string) $value] = true;
            }

            return [
                'name' => $header,
                'type' => $type,
                'unique_count' => count($unique),
            ];
        }, $headers);
    }
}
