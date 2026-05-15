<?php

namespace App\Services\Datasets;

use App\Models\Dataset;

class DatasetBoxPlotService
{
    /**
     * Compute box plot data for all numeric columns.
     *
     * @return array{columns: list<array{name: string, min: float, q1: float, median: float, q3: float, max: float, iqr: float, lowerFence: float, upperFence: float, outliers: list<float>}>}
     */
    public function compute(Dataset $dataset): array
    {
        $records = $dataset->cleaned_records ?? [];
        $profile = $dataset->profile ?? [];
        $profileColumns = $profile['columns'] ?? [];

        $numericColumns = [];
        foreach ($profileColumns as $col) {
            if (($col['type'] ?? '') === 'numeric') {
                $numericColumns[] = $col['name'];
            }
        }

        $result = [];
        foreach ($numericColumns as $colName) {
            $values = [];
            foreach ($records as $row) {
                $val = $row[$colName] ?? null;
                if ($val === null || $val === '') {
                    continue;
                }
                $values[] = (float) $val;
            }

            if (count($values) < 4) {
                continue;
            }

            sort($values);
            $result[] = $this->computeBoxPlot($colName, $values);
        }

        return ['columns' => $result];
    }

    /**
     * @param  list<float>  $values  Sorted values
     * @return array{name: string, min: float, q1: float, median: float, q3: float, max: float, iqr: float, lowerFence: float, upperFence: float, outliers: list<float>}
     */
    private function computeBoxPlot(string $name, array $values): array
    {
        $n = count($values);
        $min = $values[0];
        $max = $values[$n - 1];
        $q1 = $this->percentile($values, 25);
        $median = $this->percentile($values, 50);
        $q3 = $this->percentile($values, 75);
        $iqr = $q3 - $q1;
        $lowerFence = $q1 - 1.5 * $iqr;
        $upperFence = $q3 + 1.5 * $iqr;

        $outliers = [];
        foreach ($values as $v) {
            if ($v < $lowerFence || $v > $upperFence) {
                $outliers[] = $v;
            }
        }

        return [
            'name' => $name,
            'min' => round($min, 4),
            'q1' => round($q1, 4),
            'median' => round($median, 4),
            'q3' => round($q3, 4),
            'max' => round($max, 4),
            'iqr' => round($iqr, 4),
            'lowerFence' => round($lowerFence, 4),
            'upperFence' => round($upperFence, 4),
            'outliers' => $outliers,
        ];
    }

    /**
     * @param  list<float>  $values  Sorted values
     */
    private function percentile(array $values, float $percent): float
    {
        $n = count($values);
        if ($n === 0) {
            return 0.0;
        }

        if ($n === 1) {
            return $values[0];
        }

        $index = ($percent / 100) * ($n - 1);
        $lower = (int) floor($index);
        $upper = (int) ceil($index);

        if ($lower === $upper) {
            return $values[$lower];
        }

        $frac = $index - $lower;

        return $values[$lower] * (1 - $frac) + $values[$upper] * $frac;
    }
}
