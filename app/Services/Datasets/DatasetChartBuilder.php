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
     * @return array<string, mixed>
     */
    public function build(Dataset $dataset, string $type, ?string $xColumn, ?string $yColumn): array
    {
        $records = $dataset->cleaned_records ?? [];
        $headers = $dataset->headers ?? [];

        if ($records === []) {
            return $this->empty($type, 'Upload or clean a dataset before generating charts.');
        }

        if (! $xColumn || ! in_array($xColumn, $headers, true)) {
            return $this->empty($type, 'Select a valid category or date column.');
        }

        return match ($type) {
            'bar' => $yColumn ? $this->categoryNumericChart('bar', $records, $xColumn, $yColumn, $headers) : $this->categoryChart('bar', $records, $xColumn),
            'pie' => $this->categoryChart('pie', $records, $xColumn),
            'histogram' => $this->histogramChart($records, $xColumn),
            'line' => $this->lineChart($records, $xColumn, $yColumn, $headers),
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

        foreach ($records as $record) {
            $value = $record[$column] ?? null;

            if ($this->isBlank($value)) {
                continue;
            }

            $key = (string) $value;
            $counts[$key] = ($counts[$key] ?? 0) + 1;
        }

        if ($counts === []) {
            return $this->empty($type, 'The selected column has no values to visualize.');
        }

        arsort($counts);

        $data = collect($counts)
            ->take(self::MAX_SLICES)
            ->map(fn (int $count, string $name): array => ['name' => $name, 'value' => $count])
            ->values()
            ->all();

        return [
            'type' => $type,
            'title' => ucfirst($type).' chart for '.$column,
            'data' => $data,
            'message' => null,
            'x_column' => $column,
            'y_column' => null,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $records
     * @param  list<string>  $headers
     * @return array<string, mixed>
     */
    private function categoryNumericChart(string $type, array $records, string $xColumn, string $yColumn, array $headers): array
    {
        if (! in_array($yColumn, $headers, true)) {
            return $this->empty($type, 'Select a valid numeric value column.');
        }

        $groups = [];

        foreach ($records as $record) {
            $xValue = $record[$xColumn] ?? null;
            $yValue = $record[$yColumn] ?? null;

            if ($this->isBlank($xValue) || ! is_numeric($yValue)) {
                continue;
            }

            $key = (string) $xValue;
            $groups[$key] = ($groups[$key] ?? 0) + (float) $yValue;
        }

        if ($groups === []) {
            return $this->empty($type, 'The selected columns cannot produce a category + numeric chart.');
        }

        arsort($groups);

        $data = collect($groups)
            ->take(self::MAX_SLICES)
            ->map(fn (float $value, string $name): array => ['name' => $name, 'value' => round($value, 4)])
            ->values()
            ->all();

        return [
            'type' => $type,
            'title' => $yColumn.' by '.$xColumn,
            'data' => $data,
            'message' => null,
            'x_column' => $xColumn,
            'y_column' => $yColumn,
            'reason' => 'Category + numeric columns are summarized as totals for each category.',
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $records
     * @return array<string, mixed>
     */
    private function histogramChart(array $records, string $column): array
    {
        $numbers = [];

        foreach ($records as $record) {
            $value = $record[$column] ?? null;

            if (is_numeric($value)) {
                $numbers[] = (float) $value;
            }
        }

        if ($numbers === []) {
            return $this->empty('histogram', 'Select a numeric column to generate a histogram.');
        }

        sort($numbers);

        $minimum = $numbers[0];
        $maximum = $numbers[array_key_last($numbers)];

        if ($minimum === $maximum) {
            return [
                'type' => 'histogram',
                'title' => 'Distribution of '.$column,
                'data' => [[
                    'name' => (string) $minimum,
                    'value' => count($numbers),
                    'bin_min' => $minimum,
                    'bin_max' => $maximum,
                ]],
                'message' => null,
                'x_column' => $column,
                'y_column' => null,
                'reason' => 'Numeric columns are grouped into bins to show distribution.',
            ];
        }

        $binCount = min(self::HISTOGRAM_BINS, max(1, count(array_unique($numbers))));
        $binSize = ($maximum - $minimum) / $binCount;
        $bins = array_fill(0, $binCount, 0);

        foreach ($numbers as $number) {
            $index = min((int) floor(($number - $minimum) / $binSize), $binCount - 1);
            $bins[$index]++;
        }

        $data = [];

        foreach ($bins as $index => $count) {
            $start = $minimum + ($index * $binSize);
            $end = $index === $binCount - 1 ? $maximum : $start + $binSize;

            $data[] = [
                'name' => round($start, 2).'–'.round($end, 2),
                'value' => $count,
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
            'reason' => 'Numeric columns are grouped into bins to show distribution.',
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $records
     * @param  list<string>  $headers
     * @return array<string, mixed>
     */
    private function lineChart(array $records, string $xColumn, ?string $yColumn, array $headers): array
    {
        if ($yColumn && ! in_array($yColumn, $headers, true)) {
            return $this->empty('line', 'Select a valid numeric value column for the line chart.');
        }

        $groups = [];

        foreach ($records as $record) {
            $xValue = $record[$xColumn] ?? null;

            if ($this->isBlank($xValue)) {
                continue;
            }

            $timestamp = strtotime((string) $xValue);

            if ($timestamp === false) {
                continue;
            }

            $key = date('Y-m-d', $timestamp);
            $yValue = $yColumn ? ($record[$yColumn] ?? null) : 1;

            if (! is_numeric($yValue)) {
                continue;
            }

            $groups[$key] = ($groups[$key] ?? 0) + (float) $yValue;
        }

        if ($groups === []) {
            return $this->empty('line', 'The selected columns cannot produce a line chart.');
        }

        ksort($groups);

        return [
            'type' => 'line',
            'title' => $yColumn ? $yColumn.' by '.$xColumn : 'Records by '.$xColumn,
            'data' => collect($groups)
                ->map(fn (float $value, string $name): array => ['name' => $name, 'value' => round($value, 4)])
                ->values()
                ->all(),
            'message' => null,
            'x_column' => $xColumn,
            'y_column' => $yColumn,
            'reason' => $yColumn ? 'Date + numeric columns are summarized as a trend line.' : 'Date columns are summarized as record counts over time.',
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

        foreach ($records as $record) {
            $xValue = $record[$xColumn] ?? null;
            $yValue = $record[$yColumn] ?? null;

            if (! is_numeric($xValue) || ! is_numeric($yValue)) {
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

        return [
            'type' => 'scatter',
            'title' => $yColumn.' vs '.$xColumn,
            'data' => $data,
            'message' => null,
            'x_column' => $xColumn,
            'y_column' => $yColumn,
            'reason' => 'Numeric + numeric columns can reveal relationships as plotted points.',
        ];
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
