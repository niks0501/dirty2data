<?php

namespace App\Services\Datasets;

use App\Models\Dataset;

class DatasetMissingValueService
{
    private const int MAX_SAMPLE_ROWS = 100;

    /**
     * Build a missing value matrix (sample rows × columns).
     *
     * @return array{headers: list<string>, matrix: list<list<int>>, rowCount: int, totalRows: int}
     */
    public function buildMatrix(Dataset $dataset): array
    {
        $records = $dataset->cleaned_records ?? [];
        $headers = $dataset->headers ?? [];

        if ($records === [] || $headers === []) {
            return [
                'headers' => $headers,
                'matrix' => [],
                'rowCount' => 0,
                'totalRows' => count($records),
            ];
        }

        // Sample rows evenly if dataset is large
        $sampledRows = $records;
        $totalRows = count($records);
        if ($totalRows > self::MAX_SAMPLE_ROWS) {
            $step = (int) floor($totalRows / self::MAX_SAMPLE_ROWS);
            $sampledRows = [];
            for ($i = 0; $i < $totalRows; $i += max($step, 1)) {
                $sampledRows[] = $records[$i];
                if (count($sampledRows) >= self::MAX_SAMPLE_ROWS) {
                    break;
                }
            }
        }

        // Build bitmask matrix: 1 = missing, 0 = present
        $matrix = [];
        foreach ($sampledRows as $row) {
            $rowMask = [];
            foreach ($headers as $header) {
                $val = $row[$header] ?? null;
                $rowMask[] = ($val === null || $val === '') ? 1 : 0;
            }
            $matrix[] = $rowMask;
        }

        return [
            'headers' => $headers,
            'matrix' => $matrix,
            'rowCount' => count($sampledRows),
            'totalRows' => $totalRows,
        ];
    }

    /**
     * Compute missing value counts per column.
     *
     * @return list<array{column: string, count: int, percentage: float}>
     */
    public function columnMissings(Dataset $dataset): array
    {
        $records = $dataset->cleaned_records ?? [];
        $headers = $dataset->headers ?? [];
        $totalRows = count($records);

        $result = [];
        foreach ($headers as $header) {
            $missing = 0;
            foreach ($records as $row) {
                $val = $row[$header] ?? null;
                if ($val === null || $val === '') {
                    $missing++;
                }
            }
            $result[] = [
                'column' => $header,
                'count' => $missing,
                'percentage' => $totalRows > 0 ? round(($missing / $totalRows) * 100, 1) : 0.0,
            ];
        }

        return $result;
    }
}
