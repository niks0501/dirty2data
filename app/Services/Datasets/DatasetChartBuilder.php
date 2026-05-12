<?php

namespace App\Services\Datasets;

use App\Models\Dataset;

class DatasetChartBuilder
{
    private const int MAX_SLICES = 10;

    private const int HISTOGRAM_BINS = 8;

    private const int MAX_SCATTER_POINTS = 200;

    public function __construct(private readonly ?DatasetTypeDetector $typeDetector = null) {}

    /**
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function build(Dataset $dataset, string $type, ?string $xColumn, ?string $yColumn, array $options = []): array
    {
        $records = $dataset->cleaned_records ?? [];
        $headers = $dataset->headers ?? [];
        $aggregation = (string) ($options['aggregation'] ?? 'sum');
        $binCount = (int) ($options['bin_count'] ?? self::HISTOGRAM_BINS);
        $dateGroup = (string) ($options['date_group'] ?? 'day');

        if ($records === []) {
            return $this->empty($type, 'Upload or clean a dataset before generating charts.');
        }

        if (! $xColumn || ! in_array($xColumn, $headers, true)) {
            return $this->empty($type, 'Select a valid category or date column.');
        }

        return match ($type) {
            'bar' => $yColumn
                ? $this->categoryNumericChart('bar', $records, $xColumn, $yColumn, $headers, $aggregation)
                : $this->categoryChart('bar', $records, $xColumn),
            'pie' => $this->categoryChart('pie', $records, $xColumn),
            'histogram' => $this->histogramChart($records, $xColumn, $binCount),
            'line' => $this->lineChart($records, $xColumn, $yColumn, $headers, $dateGroup),
            'scatter' => $this->scatterChart($records, $xColumn, $yColumn, $headers),
            default => $this->empty($type, 'Select a supported chart type.'),
        };
    }

    /**
     * @param  list<array<string, mixed>>  $records
     * @return array<string, mixed>
     */
    private function categoryChart(string $type, array $records, string $column): array
    {
        $counts = [];
        $skipped = 0;

        foreach ($records as $record) {
            $value = $record[$column] ?? null;

            if ($this->isBlank($value)) {
                $skipped++;

                continue;
            }

            $key = (string) $value;
            $counts[$key] = ($counts[$key] ?? 0) + 1;
        }

        if ($counts === []) {
            return $this->empty($type, 'The selected column has no values to visualize.');
        }

        arsort($counts);
        $totalCategories = count($counts);
        $truncated = $totalCategories > self::MAX_SLICES;

        $data = collect($counts)
            ->take(self::MAX_SLICES)
            ->map(fn (int $count, string $name): array => ['name' => $name, 'value' => $count])
            ->values()
            ->all();

        if ($truncated) {
            $otherCount = collect($counts)
                ->skip(self::MAX_SLICES)
                ->sum(fn (int $count): int => $count);

            $data[] = ['name' => 'Other', 'value' => $otherCount];
        }

        return [
            'type' => $type,
            'title' => ucfirst($type).' chart for '.$column,
            'data' => $data,
            'message' => null,
            'x_column' => $column,
            'y_column' => null,
            'reason' => match ($type) {
                'pie' => 'Pie charts show each category as a share of the whole.',
                'bar' => 'Bar charts compare counts across categories.',
                default => '',
            },
            'metadata' => [
                'total_rows_used' => count($records) - $skipped,
                'missing_rows_skipped' => $skipped,
                'aggregation' => 'count',
                'truncated' => $truncated,
                'total_categories' => $totalCategories,
            ],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $records
     * @param  list<string>  $headers
     * @return array<string, mixed>
     */
    private function categoryNumericChart(string $type, array $records, string $xColumn, string $yColumn, array $headers, string $aggregation): array
    {
        if (! in_array($yColumn, $headers, true)) {
            return $this->empty($type, 'Select a valid numeric value column.');
        }

        $collected = [];
        $skipped = 0;

        foreach ($records as $record) {
            $xValue = $record[$xColumn] ?? null;
            $yValue = $record[$yColumn] ?? null;

            if ($this->isBlank($xValue) || ! is_numeric($yValue)) {
                $skipped++;

                continue;
            }

            $key = (string) $xValue;
            $collected[$key][] = (float) $yValue;
        }

        if ($collected === []) {
            return $this->empty($type, 'The selected columns cannot produce a category + numeric chart.');
        }

        $groups = [];

        foreach ($collected as $key => $values) {
            $groups[$key] = match ($aggregation) {
                'average' => round(array_sum($values) / count($values), 4),
                'count' => count($values),
                'min' => min($values),
                'max' => max($values),
                default => round(array_sum($values), 4),
            };
        }

        arsort($groups);

        $totalCategories = count($groups);
        $truncated = $totalCategories > self::MAX_SLICES;

        $data = collect($groups)
            ->take(self::MAX_SLICES)
            ->map(fn (float $value, string $name): array => ['name' => $name, 'value' => $value])
            ->values()
            ->all();

        if ($truncated) {
            $data[] = ['name' => 'Other', 'value' => 0];
        }

        $aggregationLabels = [
            'sum' => 'total',
            'average' => 'average',
            'count' => 'count',
            'min' => 'minimum',
            'max' => 'maximum',
        ];

        return [
            'type' => $type,
            'title' => $yColumn.' ('.$aggregationLabels[$aggregation].') by '.$xColumn,
            'data' => $data,
            'message' => null,
            'x_column' => $xColumn,
            'y_column' => $yColumn,
            'reason' => 'Category + numeric columns are summarized using '.($aggregationLabels[$aggregation] ?? 'sum').'.',
            'metadata' => [
                'total_rows_used' => count($records) - $skipped,
                'missing_rows_skipped' => $skipped,
                'aggregation' => $aggregation,
                'truncated' => $truncated,
                'total_categories' => $totalCategories,
            ],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $records
     * @return array<string, mixed>
     */
    private function histogramChart(array $records, string $column, int $binCount): array
    {
        $numbers = [];
        $skipped = 0;

        foreach ($records as $record) {
            $value = $record[$column] ?? null;

            if (is_numeric($value)) {
                $numbers[] = (float) $value;
            } else {
                $skipped++;
            }
        }

        if ($numbers === []) {
            return $this->empty('histogram', 'Select a numeric column to generate a histogram.');
        }

        sort($numbers);
        $count = count($numbers);
        $minimum = $numbers[0];
        $maximum = $numbers[array_key_last($numbers)];

        if ($minimum === $maximum) {
            return [
                'type' => 'histogram',
                'title' => 'Distribution of '.$column,
                'data' => [[
                    'name' => (string) $minimum,
                    'value' => $count,
                    'bin_min' => $minimum,
                    'bin_max' => $maximum,
                ]],
                'message' => null,
                'x_column' => $column,
                'y_column' => null,
                'reason' => 'All values are identical; the histogram shows a single bar.',
                'metadata' => [
                    'total_rows_used' => $count,
                    'missing_rows_skipped' => $skipped,
                    'aggregation' => 'count',
                    'truncated' => false,
                    'bin_count' => 1,
                ],
            ];
        }

        $binCount = min(max($binCount, 2), 20);
        $binCount = min($binCount, max(1, count(array_unique($numbers))));
        $binSize = ($maximum - $minimum) / $binCount;
        $bins = array_fill(0, $binCount, 0);

        foreach ($numbers as $number) {
            $index = min((int) floor(($number - $minimum) / $binSize), $binCount - 1);
            $bins[$index]++;
        }

        $data = [];

        foreach ($bins as $index => $binValue) {
            $start = $minimum + ($index * $binSize);
            $end = $index === $binCount - 1 ? $maximum : $start + $binSize;

            $data[] = [
                'name' => round($start, 2).'–'.round($end, 2),
                'value' => $binValue,
                'bin_min' => round($start, 4),
                'bin_max' => round($end, 4),
            ];
        }

        return [
            'type' => 'histogram',
            'title' => 'Distribution of '.$column,
            'data' => $data,
            'message' => null,
            'x_column' => $column,
            'y_column' => null,
            'reason' => 'Numeric values are grouped into '.$binCount.' bins to show their distribution.',
            'metadata' => [
                'total_rows_used' => $count,
                'missing_rows_skipped' => $skipped,
                'aggregation' => 'count',
                'truncated' => false,
                'bin_count' => $binCount,
            ],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $records
     * @param  list<string>  $headers
     * @return array<string, mixed>
     */
    private function lineChart(array $records, string $xColumn, ?string $yColumn, array $headers, string $dateGroup): array
    {
        if ($yColumn && ! in_array($yColumn, $headers, true)) {
            return $this->empty('line', 'Select a valid numeric value column for the line chart.');
        }

        $collected = [];
        $skipped = 0;

        foreach ($records as $record) {
            $xValue = $record[$xColumn] ?? null;

            if ($this->isBlank($xValue)) {
                $skipped++;

                continue;
            }

            $timestamp = strtotime((string) $xValue);

            if ($timestamp === false) {
                $skipped++;

                continue;
            }

            $key = match ($dateGroup) {
                'month' => date('Y-m', $timestamp),
                'year' => date('Y', $timestamp),
                default => date('Y-m-d', $timestamp),
            };

            $yValue = $yColumn ? ($record[$yColumn] ?? null) : 1;

            if (! is_numeric($yValue)) {
                $skipped++;

                continue;
            }

            $collected[$key][] = (float) $yValue;
        }

        if ($collected === []) {
            return $this->empty('line', 'The selected columns cannot produce a line chart.');
        }

        $groups = [];

        foreach ($collected as $key => $values) {
            $groups[$key] = round(array_sum($values), 4);
        }

        ksort($groups);

        $dateGroupLabels = [
            'day' => 'by day',
            'month' => 'by month',
            'year' => 'by year',
        ];

        return [
            'type' => 'line',
            'title' => $yColumn ? $yColumn.' over '.$xColumn : 'Records over '.$xColumn,
            'data' => collect($groups)
                ->map(fn (float $value, string $name): array => ['name' => $name, 'value' => $value])
                ->values()
                ->all(),
            'message' => null,
            'x_column' => $xColumn,
            'y_column' => $yColumn,
            'reason' => 'Date values are grouped '.($dateGroupLabels[$dateGroup] ?? 'by day').' to reveal trends over time.',
            'metadata' => [
                'total_rows_used' => count($records) - $skipped,
                'missing_rows_skipped' => $skipped,
                'aggregation' => 'sum',
                'truncated' => false,
                'date_group' => $dateGroup,
            ],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $records
     * @param  list<string>  $headers
     * @return array<string, mixed>
     */
    private function scatterChart(array $records, string $xColumn, ?string $yColumn, array $headers): array
    {
        if (! $yColumn || ! in_array($yColumn, $headers, true)) {
            return $this->empty('scatter', 'Select two numeric columns to generate a scatter plot.');
        }

        $data = [];
        $skipped = 0;

        foreach ($records as $record) {
            $xValue = $record[$xColumn] ?? null;
            $yValue = $record[$yColumn] ?? null;

            if (! is_numeric($xValue) || ! is_numeric($yValue)) {
                $skipped++;

                continue;
            }

            $data[] = [
                'name' => $xValue.', '.$yValue,
                'x' => (float) $xValue,
                'y' => (float) $yValue,
                'value' => (float) $yValue,
            ];

            if (count($data) === self::MAX_SCATTER_POINTS) {
                break;
            }
        }

        if ($data === []) {
            return $this->empty('scatter', 'Both selected columns must contain numeric values for a scatter plot.');
        }

        $correlation = $this->pearsonCorrelation(
            array_column($data, 'x'),
            array_column($data, 'y'),
        );

        $correlationLabel = match (true) {
            $correlation === null => '',
            abs($correlation) > 0.7 => ($correlation > 0 ? 'Strong positive' : 'Strong negative').' correlation',
            abs($correlation) > 0.4 => ($correlation > 0 ? 'Moderate positive' : 'Moderate negative').' correlation',
            default => 'Weak or no linear correlation',
        };

        return [
            'type' => 'scatter',
            'title' => $yColumn.' vs '.$xColumn,
            'data' => $data,
            'message' => null,
            'x_column' => $xColumn,
            'y_column' => $yColumn,
            'reason' => 'Two numeric columns plotted as points'.($correlationLabel !== '' ? ' — '.$correlationLabel.'.' : '.'),
            'metadata' => [
                'total_rows_used' => count($data),
                'missing_rows_skipped' => $skipped,
                'aggregation' => 'none',
                'truncated' => count($data) === self::MAX_SCATTER_POINTS,
                'correlation' => $correlation,
            ],
        ];
    }

    /**
     * @param  list<float>  $x
     * @param  list<float>  $y
     */
    private function pearsonCorrelation(array $x, array $y): ?float
    {
        $n = count($x);

        if ($n < 3) {
            return null;
        }

        $meanX = array_sum($x) / $n;
        $meanY = array_sum($y) / $n;
        $covariance = 0;
        $varianceX = 0;
        $varianceY = 0;

        for ($i = 0; $i < $n; $i++) {
            $dx = $x[$i] - $meanX;
            $dy = $y[$i] - $meanY;
            $covariance += $dx * $dy;
            $varianceX += $dx * $dx;
            $varianceY += $dy * $dy;
        }

        if ($varianceX === 0.0 || $varianceY === 0.0) {
            return null;
        }

        return round($covariance / sqrt($varianceX * $varianceY), 4);
    }

    /**
     * @return array<string, mixed>
     */
    private function empty(string $type, string $message): array
    {
        return [
            'type' => $type,
            'title' => 'Chart preview',
            'data' => [],
            'message' => $message,
            'x_column' => null,
            'y_column' => null,
            'reason' => null,
            'metadata' => null,
        ];
    }

    private function isBlank(mixed $value): bool
    {
        return $this->detector()->isBlank($value);
    }

    private function detector(): DatasetTypeDetector
    {
        return $this->typeDetector ?? new DatasetTypeDetector;
    }
}
