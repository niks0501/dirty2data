<?php

namespace App\Services\Datasets;

use App\Models\Dataset;

class DatasetComparisonBuilder
{
    public function __construct(private readonly ?DatasetTypeDetector $typeDetector = null) {}

    /**
     * Build a side-by-side comparison between original and cleaned dataset records.
     *
     * Each row shows every cell with before/after values and a per-cell change status.
     * Rows that exist only in original are marked 'removed'; rows only in cleaned are
     * marked 'added'. Matched rows are compared cell-by-cell and classified as
     * 'unchanged' or 'changed'.
     *
     * @return array{rows: list<array<string, mixed>>, summary: array<string, mixed>, pagination: array<string, mixed>}
     */
    public function build(Dataset $dataset, int $page = 1, int $perPage = 15): array
    {
        $originalRecords = (array) ($dataset->original_records ?? []);
        $cleanedRecords = (array) ($dataset->cleaned_records ?? []);
        $headers = (array) ($dataset->headers ?? []);
        $cleaningLog = (array) ($dataset->cleaning_log ?? []);

        $rows = $this->buildComparisonRows($originalRecords, $cleanedRecords, $headers);
        $summary = $this->buildSummary($originalRecords, $cleanedRecords, $cleaningLog, $rows);
        $paginated = $this->paginate($rows, $page, $perPage);

        return [
            'rows' => $paginated['items'],
            'summary' => $summary,
            'pagination' => $paginated['meta'],
        ];
    }

    /**
     * Build comparison rows by matching original records against cleaned records.
     *
     * Uses content-hash matching so that reordered rows still find their original
     * counterpart. Rows with the same hash are considered identical; rows that hash-
     * match but differ at the cell level are flagged as 'changed'. Unmatched originals
     * become 'removed' rows and unmatched cleaned rows become 'added' rows.
     *
     * @param  list<array<string, mixed>>  $originalRecords
     * @param  list<array<string, mixed>>  $cleanedRecords
     * @param  list<string>  $headers
     * @return list<array<string, mixed>>
     */
    private function buildComparisonRows(array $originalRecords, array $cleanedRecords, array $headers): array
    {
        // Build a content-hash index of original records for fast lookup.
        // Each hash may map to multiple indices when duplicate rows exist.
        /** @var array<string, list<int>> */
        $hashIndex = [];

        foreach ($originalRecords as $idx => $row) {
            $hash = $this->hashRow($row);
            $hashIndex[$hash][] = $idx;
        }

        /** @var array<int, true> */
        $matchedOriginals = [];
        $rows = [];

        foreach ($cleanedRecords as $cleanedIdx => $cleanedRow) {
            $cleanedHash = $this->hashRow($cleanedRow);
            $matchedOriginalIdx = null;
            $status = 'added';

            // 1. Try exact hash match in the original pool
            if (! empty($hashIndex[$cleanedHash])) {
                $matchedOriginalIdx = array_shift($hashIndex[$cleanedHash]);
                $matchedOriginals[$matchedOriginalIdx] = true;
                $originalRow = $originalRecords[$matchedOriginalIdx];
                $status = $originalRow === $cleanedRow ? 'unchanged' : 'changed';
            } elseif ($cleanedIdx < count($originalRecords) && ! isset($matchedOriginals[$cleanedIdx])) {
                // 2. Fallback: no hash match, but there is an unmatched original at the
                //    same position — the row was likely modified in-place.
                $matchedOriginalIdx = $cleanedIdx;
                $matchedOriginals[$matchedOriginalIdx] = true;
                $status = 'changed';
            }

            if ($matchedOriginalIdx !== null) {
                $originalRow = $originalRecords[$matchedOriginalIdx];
                $rows[] = $this->buildMatchedRow($originalRow, $cleanedRow, $headers, $status);
            } else {
                $rows[] = $this->buildAddedRow($cleanedRow, $headers);
            }
        }

        // 3. Any original rows still unmatched are treated as removed.
        foreach ($originalRecords as $origIdx => $originalRow) {
            if (! isset($matchedOriginals[$origIdx])) {
                $rows[] = $this->buildRemovedRow($originalRow, $headers);
            }
        }

        // Number each row sequentially (1-based).
        $numbered = [];
        $idx = 1;

        foreach ($rows as $row) {
            $row['rowNumber'] = $idx++;
            $numbered[] = $row;
        }

        return $numbered;
    }

    /**
     * Build a comparison entry for a row that exists in both datasets.
     *
     * @param  array<string, mixed>  $originalRow
     * @param  array<string, mixed>  $cleanedRow
     * @param  list<string>  $headers
     * @param  'unchanged'|'changed'  $status
     * @return array<string, mixed>
     */
    private function buildMatchedRow(array $originalRow, array $cleanedRow, array $headers, string $status): array
    {
        $cells = [];

        foreach ($headers as $header) {
            $before = $originalRow[$header] ?? null;
            $after = $cleanedRow[$header] ?? null;

            $cells[] = [
                'header' => $header,
                'value' => $after,
                'changed' => ($before !== $after),
            ];
        }

        return [
            'status' => $status === 'changed' ? 'modified' : $status,
            'cells' => $cells,
        ];
    }

    /**
     * Build a comparison entry for a row that exists only in the cleaned dataset.
     *
     * @param  array<string, mixed>  $cleanedRow
     * @param  list<string>  $headers
     * @return array<string, mixed>
     */
    private function buildAddedRow(array $cleanedRow, array $headers): array
    {
        $cells = [];

        foreach ($headers as $header) {
            $cells[] = [
                'header' => $header,
                'value' => $cleanedRow[$header] ?? null,
                'changed' => true,
            ];
        }

        return [
            'status' => 'added',
            'cells' => $cells,
        ];
    }

    /**
     * Build a comparison entry for a row that exists only in the original dataset.
     *
     * @param  array<string, mixed>  $originalRow
     * @param  list<string>  $headers
     * @return array<string, mixed>
     */
    private function buildRemovedRow(array $originalRow, array $headers): array
    {
        $cells = [];

        foreach ($headers as $header) {
            $cells[] = [
                'header' => $header,
                'value' => $originalRow[$header] ?? null,
                'changed' => true,
            ];
        }

        return [
            'status' => 'removed',
            'cells' => $cells,
        ];
    }

    /**
     * Compute summary statistics from the comparison rows and cleaning log.
     *
     * @param  list<array<string, mixed>>  $originalRecords
     * @param  list<array<string, mixed>>  $cleanedRecords
     * @param  list<array<string, mixed>>  $cleaningLog
     * @param  list<array<string, mixed>>  $rows
     * @return array<string, mixed>
     */
    private function buildSummary(array $originalRecords, array $cleanedRecords, array $cleaningLog, array $rows): array
    {
        $rowsModified = 0;
        $rowsAdded = 0;
        $rowsRemoved = 0;
        $rowsUnchanged = 0;
        $cellsChanged = 0;

        foreach ($rows as $row) {
            match ($row['status']) {
                'unchanged' => $rowsUnchanged++,
                'modified' => $rowsModified++,
                'added' => $rowsAdded++,
                'removed' => $rowsRemoved++,
            };

            foreach ($row['cells'] as $cell) {
                if (! empty($cell['changed'])) {
                    $cellsChanged++;
                }
            }
        }

        $duplicatesRemoved = 0;
        $missingValuesFilled = 0;

        foreach ($cleaningLog as $entry) {
            $operation = $entry['operation'] ?? '';

            match ($operation) {
                'remove_duplicates' => $duplicatesRemoved += (int) ($entry['summary']['removed_rows'] ?? $entry['removed_rows'] ?? 0),
                'fill_missing' => $missingValuesFilled += (int) ($entry['summary']['filled_cells'] ?? $entry['filled_cells'] ?? 0),
                default => null,
            };
        }

        return [
            'totalRows' => count($cleanedRecords),
            'rowsModified' => $rowsModified,
            'rowsAdded' => $rowsAdded,
            'rowsRemoved' => $rowsRemoved,
            'rowsUnchanged' => $rowsUnchanged,
            'cellsChanged' => $cellsChanged,
            'duplicatesRemoved' => $duplicatesRemoved,
            'missingValuesFilled' => $missingValuesFilled,
        ];
    }

    /**
     * Paginate a list of comparison rows.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return array{items: list<array<string, mixed>>, meta: array<string, mixed>}
     */
    private function paginate(array $rows, int $page, int $perPage): array
    {
        $total = count($rows);
        $lastPage = max(1, (int) ceil($total / max(1, $perPage)));
        $page = min(max(1, $page), $lastPage);
        $offset = ($page - 1) * $perPage;
        $items = array_slice($rows, $offset, $perPage);

        return [
            'items' => $items,
            'meta' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => $lastPage,
            ],
        ];
    }

    /**
     * Compute a stable content hash for a single data row.
     *
     * Uses MD5 over a serialized representation so that identical row content always
     * produces the same hash regardless of key order within the row.
     *
     * @param  array<string, mixed>  $row
     */
    private function hashRow(array $row): string
    {
        // Sort by key to guarantee stable hashing regardless of key order
        ksort($row);

        return md5(serialize($row));
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
