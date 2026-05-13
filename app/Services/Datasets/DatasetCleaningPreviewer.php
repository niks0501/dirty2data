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
     * @param  list<array<string, mixed>>  $steps
     * @return array<string, mixed>
     */
    public function previewPipeline(Dataset $dataset, array $steps): array
    {
        $before = $dataset->cleaned_records ?? [];
        $result = $this->cleaner->cleanPipeline($dataset, $steps);
        $after = $result['records'];

        return [
            'operation' => 'pipeline',
            'summary' => $result['summary'],
            'message' => count($steps).' recommended cleaning steps will be previewed as one approved action.',
            'affected_count' => $this->affectedCount($result['summary']),
            'changed_rows' => $this->changedRows($before, $after),
            'will_change_dataset' => $before !== $after,
            'steps' => $steps,
            'step_logs' => $result['logs'],
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
        foreach (['affected_rows', 'affected_cells', 'removed_rows', 'filled_cells', 'converted_cells', 'standardized_cells', 'replaced_cells', 'pattern_removed_cells', 'extracted_cells', 'parsed_cells', 'cleaned_cells', 'split_rows', 'merged_rows'] as $key) {
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
            'replace_values' => ($summary['replaced_cells'] ?? 0).' matching values will be replaced.',
            'remove_pattern' => ($summary['pattern_removed_cells'] ?? 0).' values will have the pattern removed.',
            'extract_number' => ($summary['extracted_cells'] ?? 0).' values will be reduced to their numeric part.',
            'split_column' => ($summary['split_rows'] ?? 0).' rows will be split into new columns.',
            'parse_list' => ($summary['parsed_cells'] ?? 0).' list-like values will be standardized.',
            'rename_column' => 'The selected column will be renamed.',
            'remove_column' => 'The selected column will be removed from the working copy.',
            'merge_columns' => ($summary['merged_rows'] ?? 0).' rows will receive merged column values.',
            'numeric_range_filter' => ($summary['removed_rows'] ?? 0).' rows outside the numeric range will be removed.',
            'date_format_convert' => ($summary['converted_cells'] ?? 0).' dates will be reformatted.',
            'remove_special_characters' => ($summary['cleaned_cells'] ?? 0).' values will have special characters removed.',
            default => 'The selected cleaning action will be previewed.',
        };
    }
}
