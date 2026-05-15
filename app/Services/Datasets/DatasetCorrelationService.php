<?php

namespace App\Services\Datasets;

use App\Models\Dataset;

class DatasetCorrelationService
{
    /**
     * Compute pairwise Pearson correlations for all numeric columns.
     *
     * @return array{columns: list<string>, matrix: list<list<float|null>>}
     */
    public function compute(Dataset $dataset): array
    {
        $records = $dataset->cleaned_records ?? [];
        $profile = $dataset->profile ?? [];
        $profileColumns = $profile['columns'] ?? [];

        // Identify numeric columns from profile
        $numericColumns = [];
        foreach ($profileColumns as $col) {
            if (($col['type'] ?? '') === 'numeric') {
                $numericColumns[] = $col['name'];
            }
        }

        if (count($numericColumns) < 2 || $records === []) {
            return [
                'columns' => $numericColumns,
                'matrix' => [],
            ];
        }

        // Build vectors for each numeric column
        $vectors = [];
        foreach ($numericColumns as $colName) {
            $values = [];
            foreach ($records as $row) {
                $val = $row[$colName] ?? null;
                if ($val === null || $val === '') {
                    continue;
                }
                $values[] = (float) $val;
            }
            $vectors[$colName] = $values;
        }

        // Compute pairwise correlation matrix
        $n = count($numericColumns);
        $matrix = [];
        for ($i = 0; $i < $n; $i++) {
            $row = [];
            for ($j = 0; $j < $n; $j++) {
                if ($i === $j) {
                    $row[] = 1.0;
                } elseif ($j < $i) {
                    // Mirror from lower triangle
                    $row[] = $matrix[$j][$i];
                } else {
                    $row[] = $this->pearson(
                        $vectors[$numericColumns[$i]],
                        $vectors[$numericColumns[$j]],
                    );
                }
            }
            $matrix[] = $row;
        }

        return [
            'columns' => $numericColumns,
            'matrix' => $matrix,
        ];
    }

    /**
     * Compute Pearson correlation coefficient between two equal-length numeric arrays.
     *
     * @param  list<float>  $x
     * @param  list<float>  $y
     */
    private function pearson(array $x, array $y): ?float
    {
        $n = count($x);
        if ($n < 3 || count($y) < 3) {
            return null;
        }

        // Align vectors: only include indices where both have valid values
        $alignedX = [];
        $alignedY = [];
        $len = min(count($x), count($y));
        for ($i = 0; $i < $len; $i++) {
            if (is_numeric($x[$i]) && is_numeric($y[$i])) {
                $alignedX[] = $x[$i];
                $alignedY[] = $y[$i];
            }
        }

        $n = count($alignedX);
        if ($n < 3) {
            return null;
        }

        $sumX = array_sum($alignedX);
        $sumY = array_sum($alignedY);
        $sumXY = 0.0;
        $sumX2 = 0.0;
        $sumY2 = 0.0;

        for ($i = 0; $i < $n; $i++) {
            $sumXY += $alignedX[$i] * $alignedY[$i];
            $sumX2 += $alignedX[$i] ** 2;
            $sumY2 += $alignedY[$i] ** 2;
        }

        $denom = sqrt(
            ($n * $sumX2 - $sumX ** 2) *
            ($n * $sumY2 - $sumY ** 2)
        );

        if ($denom == 0) {
            return null;
        }

        return round(($n * $sumXY - $sumX * $sumY) / $denom, 4);
    }
}
