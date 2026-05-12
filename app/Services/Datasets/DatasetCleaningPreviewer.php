<?php

namespace App\Services\Datasets;

use App\Models\Dataset;

class DatasetCleaningPreviewer
{
    private const int MAX_CHANGE_SAMPLES = 5;

    public function __construct(private readonly DatasetCleaner $cleaner) {}

    /**
     * @return array<string, mixed>
     */
    public function preview(Dataset $dataset, array $input): array
    {
        $before = $dataset->cleaned_records ?? [];
        $result = $this->cleaner->clean($dataset, $input);
        $after = $result['records'];

        return [
            'operation' => (string) ($input['operation'] ?? ''),
            'summary' => $result['summary'],
            'message' => $this->message((string) ($input['operation'] ?? ''), $result['summary']),
            'affected_count' => $this->affectedCount($result['summary']),
            'changed_rows' => $this->changedRows($before, $after),
            'will_change_dataset' => $before !== $after,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $before
     * @param  list<array<string, mixed>>  $after
     * @return list<array<string, mixed>>
     */
    private function changedRows(array $before, array $after): array
    {
        $changes = [];
        $limit = max(count($before), count($after));

        for ($index = 0; $index < $limit; $index++) {
            $beforeRow = $before[$index] ?? null;
            $afterRow = $after[$index] ?? null;

            if ($beforeRow === $afterRow) {
                continue;
            }

            $changes[] = [
                'row_number' => $index + 1,
                'status' => $this->rowStatus($beforeRow, $afterRow),
                'before' => $beforeRow,
                'after' => $afterRow,
            ];

            if (count($changes) === self::MAX_CHANGE_SAMPLES) {
                break;
            }
        }

        return $changes;
    }

    private function rowStatus(?array $beforeRow, ?array $afterRow): string
    {
        if ($beforeRow === null) {
            return 'added';
        }

        if ($afterRow === null) {
            return 'removed';
        }

        return 'changed';
    }

    /**
     * @param  array<string, mixed>  $summary
     */
    private function affectedCount(array $summary): int
    {
        foreach (['removed_rows', 'filled_cells', 'converted_cells', 'standardized_cells'] as $key) {
            if (isset($summary[$key]) && is_numeric($summary[$key])) {
                return (int) $summary[$key];
            }
        }

        return 0;
    }

    /**
     * @param  array<string, mixed>  $summary
     */
    private function message(string $operation, array $summary): string
    {
        return match ($operation) {
            'remove_duplicates' => ($summary['removed_rows'] ?? 0).' duplicate rows will be removed.',
            'fill_missing' => ($summary['filled_cells'] ?? 0).' missing values will be filled in '.($summary['column'] ?? 'the selected column').'.',
            'convert_type' => ($summary['converted_cells'] ?? 0).' values will be converted to '.($summary['target_type'] ?? 'the selected type').'.',
            'standardize_text' => ($summary['standardized_cells'] ?? 0).' text values will be standardized.',
            'filter_invalid' => ($summary['removed_rows'] ?? 0).' invalid rows will be removed.',
            default => 'The selected cleaning action will be previewed.',
        };
    }
}
