<?php

namespace App\Services\Datasets;

use App\Models\Dataset;

class DatasetCleaner
{
    /**
     * @param  list<array<string, mixed>>|null  $customRecords
     * @return array{records: list<array<string, mixed>>, log: array<string, mixed>, summary: array<string, mixed>}
     */
    public function clean(Dataset $dataset, array $input, ?array $customRecords = null): array
    {
        $records = $customRecords ?? ($dataset->cleaned_records ?? []);
        $operation = (string) ($input['operation'] ?? '');

        return match ($operation) {
            'remove_duplicates' => $this->removeDuplicates($records),
            'fill_missing' => $this->fillMissing($records, $input),
            'convert_type' => $this->convertType($records, $input),
            'standardize_text' => $this->standardizeText($records, $input),
            'filter_invalid' => $this->filterInvalidRows($records, $input),
            default => throw new \InvalidArgumentException('Unsupported cleaning operation.'),
        };
    }

    /**
     * @param  list<array<string, mixed>>  $records
     * @return array{records: list<array<string, mixed>>, log: array<string, mixed>, summary: array<string, mixed>}
     */
    private function removeDuplicates(array $records): array
    {
        $seen = [];
        $cleaned = [];
        $removed = 0;

        foreach ($records as $record) {
            $keyRecord = $record;
            ksort($keyRecord);
            $key = json_encode($keyRecord);

            if (isset($seen[$key])) {
                $removed++;

                continue;
            }

            $seen[$key] = true;
            $cleaned[] = $record;
        }

        return $this->result($cleaned, 'remove_duplicates', ['removed_rows' => $removed]);
    }

    /**
     * @param  list<array<string, mixed>>  $records
     * @param  array<string, mixed>  $input
     * @return array{records: list<array<string, mixed>>, log: array<string, mixed>, summary: array<string, mixed>}
     */
    private function fillMissing(array $records, array $input): array
    {
        $column = (string) ($input['column'] ?? '');
        $method = (string) ($input['method'] ?? '');

        $this->ensureColumnExists($records, $column);

        $replacement = $this->replacementValue($records, $column, $method, $input['value'] ?? null);
        $filled = 0;

        foreach ($records as &$record) {
            if ($this->isBlank($record[$column] ?? null)) {
                $record[$column] = $replacement;
                $filled++;
            }
        }

        unset($record);

        return $this->result($records, 'fill_missing', [
            'column' => $column,
            'method' => $method,
            'replacement' => $replacement,
            'filled_cells' => $filled,
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $records
     */
    private function replacementValue(array $records, string $column, string $method, mixed $customValue): mixed
    {
        $values = array_values(array_filter(
            array_map(fn (array $record): mixed => $record[$column] ?? null, $records),
            fn (mixed $value): bool => ! $this->isBlank($value),
        ));

        return match ($method) {
            'blank' => '',
            'custom' => $customValue,
            'mean' => $this->mean($values),
            'median' => $this->median($values),
            'mode' => $this->mode($values),
            default => throw new \InvalidArgumentException('Unsupported missing value fill method.'),
        };
    }

    /**
     * @param  list<array<string, mixed>>  $records
     * @param  array<string, mixed>  $input
     * @return array{records: list<array<string, mixed>>, log: array<string, mixed>, summary: array<string, mixed>}
     */
    private function convertType(array $records, array $input): array
    {
        $column = (string) ($input['column'] ?? '');
        $targetType = (string) ($input['target_type'] ?? '');

        $this->ensureColumnExists($records, $column);

        $converted = 0;

        foreach ($records as &$record) {
            $value = $record[$column] ?? null;

            if ($this->isBlank($value)) {
                continue;
            }

            $record[$column] = $this->convertValue($value, $targetType);
            $converted++;
        }

        unset($record);

        return $this->result($records, 'convert_type', [
            'column' => $column,
            'target_type' => $targetType,
            'converted_cells' => $converted,
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $records
     * @param  array<string, mixed>  $input
     * @return array{records: list<array<string, mixed>>, log: array<string, mixed>, summary: array<string, mixed>}
     */
    private function standardizeText(array $records, array $input): array
    {
        $column = (string) ($input['column'] ?? '');
        $format = (string) ($input['text_format'] ?? 'trim');

        $this->ensureColumnExists($records, $column);

        $standardized = 0;

        foreach ($records as &$record) {
            $value = $record[$column] ?? null;

            if ($this->isBlank($value)) {
                continue;
            }

            $updated = $this->standardizeValue((string) $value, $format);

            if ($updated !== $value) {
                $record[$column] = $updated;
                $standardized++;
            }
        }

        unset($record);

        return $this->result($records, 'standardize_text', [
            'column' => $column,
            'text_format' => $format,
            'standardized_cells' => $standardized,
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $records
     * @param  array<string, mixed>  $input
     * @return array{records: list<array<string, mixed>>, log: array<string, mixed>, summary: array<string, mixed>}
     */
    private function filterInvalidRows(array $records, array $input): array
    {
        $column = (string) ($input['column'] ?? '');
        $targetType = (string) ($input['target_type'] ?? 'non_blank');

        $this->ensureColumnExists($records, $column);

        $cleaned = [];
        $removed = 0;

        foreach ($records as $record) {
            if (! $this->isValidForType($record[$column] ?? null, $targetType)) {
                $removed++;

                continue;
            }

            $cleaned[] = $record;
        }

        return $this->result($cleaned, 'filter_invalid', [
            'column' => $column,
            'target_type' => $targetType,
            'removed_rows' => $removed,
        ]);
    }

    private function convertValue(mixed $value, string $targetType): mixed
    {
        return match ($targetType) {
            'text' => (string) $value,
            'numeric' => is_numeric($value) ? (float) $value : throw new \InvalidArgumentException('Selected column contains values that cannot be converted to numeric.'),
            'date' => strtotime((string) $value) !== false ? date('Y-m-d', strtotime((string) $value)) : throw new \InvalidArgumentException('Selected column contains values that cannot be converted to date.'),
            'boolean' => $this->toBoolean($value),
            default => throw new \InvalidArgumentException('Unsupported target type.'),
        };
    }

    private function standardizeValue(string $value, string $format): string
    {
        $trimmed = trim($value);

        return match ($format) {
            'trim' => $trimmed,
            'lowercase' => mb_strtolower($trimmed),
            'uppercase' => mb_strtoupper($trimmed),
            'title' => mb_convert_case($trimmed, MB_CASE_TITLE, 'UTF-8'),
            default => throw new \InvalidArgumentException('Unsupported text format.'),
        };
    }

    private function isValidForType(mixed $value, string $targetType): bool
    {
        if ($targetType === 'non_blank') {
            return ! $this->isBlank($value);
        }

        if ($this->isBlank($value)) {
            return false;
        }

        return match ($targetType) {
            'numeric' => is_numeric($value),
            'date' => is_string($value) && ! is_numeric($value) && strtotime($value) !== false,
            'boolean' => $this->isBooleanLike($value),
            'text' => is_scalar($value),
            default => throw new \InvalidArgumentException('Unsupported invalid row filter type.'),
        };
    }

    private function toBoolean(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        $normalized = mb_strtolower(trim((string) $value));

        return match ($normalized) {
            'true', 'yes', '1' => true,
            'false', 'no', '0' => false,
            default => throw new \InvalidArgumentException('Selected column contains values that cannot be converted to boolean.'),
        };
    }

    private function isBooleanLike(mixed $value): bool
    {
        if (is_bool($value)) {
            return true;
        }

        return is_string($value) && in_array(mb_strtolower(trim($value)), ['true', 'false', 'yes', 'no', '1', '0'], true);
    }

    /**
     * @param  list<mixed>  $values
     */
    private function mean(array $values): float
    {
        $numbers = $this->numbers($values);

        if ($numbers === []) {
            throw new \InvalidArgumentException('Mean fill requires numeric values in the selected column.');
        }

        return round(array_sum($numbers) / count($numbers), 4);
    }

    /**
     * @param  list<mixed>  $values
     */
    private function median(array $values): float
    {
        $numbers = $this->numbers($values);

        if ($numbers === []) {
            throw new \InvalidArgumentException('Median fill requires numeric values in the selected column.');
        }

        sort($numbers);
        $count = count($numbers);
        $middle = intdiv($count, 2);

        if ($count % 2 === 1) {
            return $numbers[$middle];
        }

        return round(($numbers[$middle - 1] + $numbers[$middle]) / 2, 4);
    }

    /**
     * @param  list<mixed>  $values
     */
    private function mode(array $values): mixed
    {
        if ($values === []) {
            return '';
        }

        $counts = [];

        foreach ($values as $value) {
            $key = (string) $value;
            $counts[$key] = ($counts[$key] ?? 0) + 1;
        }

        arsort($counts);

        return array_key_first($counts);
    }

    /**
     * @param  list<mixed>  $values
     * @return list<float>
     */
    private function numbers(array $values): array
    {
        if (! array_reduce($values, fn (bool $carry, mixed $value): bool => $carry && is_numeric($value), true)) {
            return [];
        }

        return array_map(fn (mixed $value): float => (float) $value, $values);
    }

    /**
     * Push the current cleaned_records onto the snapshots stack before a cleaning operation.
     *
     * @return list<list<array<string, mixed>>>
     */
    public function pushSnapshot(Dataset $dataset): array
    {
        $snapshots = $dataset->cleaning_snapshots ?? [];
        $currentRecords = $dataset->cleaned_records ?? [];

        array_unshift($snapshots, $currentRecords);

        if (count($snapshots) > 10) {
            $snapshots = array_slice($snapshots, 0, 10);
        }

        return $snapshots;
    }

    /**
     * @param  list<array<string, mixed>>  $records
     * @param  array<string, mixed>  $summary
     * @return array{records: list<array<string, mixed>>, log: array<string, mixed>, summary: array<string, mixed>}
     */
    private function result(array $records, string $operation, array $summary): array
    {
        return [
            'records' => $records,
            'log' => [
                'operation' => $operation,
                'summary' => $summary,
                'applied_at' => now()->toISOString(),
            ],
            'summary' => $summary,
        ];
    }

    private function isBlank(mixed $value): bool
    {
        return $value === null || (is_string($value) && trim($value) === '');
    }

    /**
     * @param  list<array<string, mixed>>  $records
     */
    private function ensureColumnExists(array $records, string $column): void
    {
        if ($column === '') {
            throw new \InvalidArgumentException('Select a column for this cleaning action.');
        }

        if ($records === []) {
            return;
        }

        if (! array_key_exists($column, $records[0])) {
            throw new \InvalidArgumentException('Selected column was not found in this dataset.');
        }
    }
}
