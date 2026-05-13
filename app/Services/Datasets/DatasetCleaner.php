<?php

namespace App\Services\Datasets;

use App\Models\Dataset;

class DatasetCleaner
{
    public const array OPERATIONS = [
        'remove_duplicates',
        'fill_missing',
        'convert_type',
        'standardize_text',
        'filter_invalid',
        'replace_values',
        'remove_pattern',
        'extract_number',
        'split_column',
        'parse_list',
        'rename_column',
        'remove_column',
        'merge_columns',
        'numeric_range_filter',
        'date_format_convert',
        'remove_special_characters',
    ];

    /**
     * @param  list<array<string, mixed>>|null  $customRecords
     * @param  list<string>|null  $customHeaders
     * @return array{records: list<array<string, mixed>>, headers: list<string>, log: array<string, mixed>, summary: array<string, mixed>}
     */
    public function clean(Dataset $dataset, array $input, ?array $customRecords = null, ?array $customHeaders = null): array
    {
        $records = $customRecords ?? ($dataset->cleaned_records ?? []);
        $headers = $customHeaders ?? ($dataset->headers ?? []);
        $operation = (string) ($input['operation'] ?? '');

        return match ($operation) {
            'remove_duplicates' => $this->removeDuplicates($records, $headers, $input),
            'fill_missing' => $this->fillMissing($records, $headers, $input),
            'convert_type' => $this->convertType($records, $headers, $input),
            'standardize_text' => $this->standardizeText($records, $headers, $input),
            'filter_invalid' => $this->filterInvalidRows($records, $headers, $input),
            'replace_values' => $this->replaceValues($records, $headers, $input),
            'remove_pattern' => $this->removePattern($records, $headers, $input),
            'extract_number' => $this->extractNumber($records, $headers, $input),
            'split_column' => $this->splitColumn($records, $headers, $input),
            'parse_list' => $this->parseList($records, $headers, $input),
            'rename_column' => $this->renameColumn($records, $headers, $input),
            'remove_column' => $this->removeColumn($records, $headers, $input),
            'merge_columns' => $this->mergeColumns($records, $headers, $input),
            'numeric_range_filter' => $this->numericRangeFilter($records, $headers, $input),
            'date_format_convert' => $this->dateFormatConvert($records, $headers, $input),
            'remove_special_characters' => $this->removeSpecialCharacters($records, $headers, $input),
            default => throw new \InvalidArgumentException('Unsupported cleaning operation.'),
        };
    }

    /**
     * @param  list<array<string, mixed>>  $steps
     * @param  list<array<string, mixed>>|null  $customRecords
     * @param  list<string>|null  $customHeaders
     * @param  array<string, mixed>  $logContext
     * @return array{records: list<array<string, mixed>>, headers: list<string>, logs: list<array<string, mixed>>, summary: array<string, mixed>}
     */
    public function cleanPipeline(Dataset $dataset, array $steps, ?array $customRecords = null, ?array $customHeaders = null, array $logContext = []): array
    {
        $records = $customRecords ?? ($dataset->cleaned_records ?? []);
        $headers = $customHeaders ?? ($dataset->headers ?? []);
        $logs = [];
        $summaries = [];

        foreach ($steps as $step) {
            $input = $this->normalizeStep($step);
            $result = $this->clean($dataset, $input, $records, $headers);
            $records = $result['records'];
            $headers = $result['headers'];
            $logs[] = array_merge($result['log'], $logContext);
            $summaries[] = $result['summary'];
        }

        return [
            'records' => $records,
            'headers' => $headers,
            'logs' => $logs,
            'summary' => [
                'steps' => count($steps),
                'affected_rows' => array_sum(array_map(fn (array $summary): int => (int) ($summary['affected_rows'] ?? $summary['removed_rows'] ?? 0), $summaries)),
                'affected_cells' => array_sum(array_map(fn (array $summary): int => (int) ($summary['affected_cells'] ?? 0), $summaries)),
                'step_summaries' => $summaries,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $step
     * @return array<string, mixed>
     */
    public function normalizeStep(array $step): array
    {
        $parameters = $step['parameters'] ?? [];

        if (! is_array($parameters)) {
            $parameters = [];
        }

        return array_merge($parameters, [
            'operation' => (string) ($step['operation'] ?? ''),
            'column' => (string) ($step['column'] ?? ($parameters['column'] ?? '')),
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $records
     * @param  list<string>  $headers
     * @param  array<string, mixed>  $input
     * @return array{records: list<array<string, mixed>>, headers: list<string>, log: array<string, mixed>, summary: array<string, mixed>}
     */
    private function removeDuplicates(array $records, array $headers, array $input): array
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

        return $this->result($cleaned, $headers, 'remove_duplicates', ['removed_rows' => $removed, 'affected_rows' => $removed], $input);
    }

    /**
     * @param  list<array<string, mixed>>  $records
     * @param  list<string>  $headers
     * @param  array<string, mixed>  $input
     * @return array{records: list<array<string, mixed>>, headers: list<string>, log: array<string, mixed>, summary: array<string, mixed>}
     */
    private function fillMissing(array $records, array $headers, array $input): array
    {
        $column = (string) ($input['column'] ?? '');
        $method = (string) ($input['method'] ?? '');

        $this->ensureColumnExists($records, $headers, $column);

        $replacement = $this->replacementValue($records, $column, $method, $input['value'] ?? null);
        $filled = 0;

        foreach ($records as &$record) {
            if ($this->isBlank($record[$column] ?? null)) {
                $record[$column] = $replacement;
                $filled++;
            }
        }

        unset($record);

        return $this->result($records, $headers, 'fill_missing', [
            'column' => $column,
            'method' => $method,
            'replacement' => $replacement,
            'filled_cells' => $filled,
            'affected_cells' => $filled,
        ], $input);
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
     * @param  list<string>  $headers
     * @param  array<string, mixed>  $input
     * @return array{records: list<array<string, mixed>>, headers: list<string>, log: array<string, mixed>, summary: array<string, mixed>}
     */
    private function convertType(array $records, array $headers, array $input): array
    {
        $column = (string) ($input['column'] ?? '');
        $targetType = (string) ($input['target_type'] ?? '');

        $this->ensureColumnExists($records, $headers, $column);

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

        return $this->result($records, $headers, 'convert_type', [
            'column' => $column,
            'target_type' => $targetType,
            'converted_cells' => $converted,
            'affected_cells' => $converted,
        ], $input);
    }

    /**
     * @param  list<array<string, mixed>>  $records
     * @param  list<string>  $headers
     * @param  array<string, mixed>  $input
     * @return array{records: list<array<string, mixed>>, headers: list<string>, log: array<string, mixed>, summary: array<string, mixed>}
     */
    private function standardizeText(array $records, array $headers, array $input): array
    {
        $column = (string) ($input['column'] ?? '');
        $format = (string) ($input['text_format'] ?? 'trim');

        $this->ensureColumnExists($records, $headers, $column);

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

        return $this->result($records, $headers, 'standardize_text', [
            'column' => $column,
            'text_format' => $format,
            'standardized_cells' => $standardized,
            'affected_cells' => $standardized,
        ], $input);
    }

    /**
     * @param  list<array<string, mixed>>  $records
     * @param  list<string>  $headers
     * @param  array<string, mixed>  $input
     * @return array{records: list<array<string, mixed>>, headers: list<string>, log: array<string, mixed>, summary: array<string, mixed>}
     */
    private function filterInvalidRows(array $records, array $headers, array $input): array
    {
        $column = (string) ($input['column'] ?? '');
        $targetType = (string) ($input['target_type'] ?? 'non_blank');

        $this->ensureColumnExists($records, $headers, $column);

        $cleaned = [];
        $removed = 0;

        foreach ($records as $record) {
            if (! $this->isValidForType($record[$column] ?? null, $targetType)) {
                $removed++;

                continue;
            }

            $cleaned[] = $record;
        }

        return $this->result($cleaned, $headers, 'filter_invalid', [
            'column' => $column,
            'target_type' => $targetType,
            'removed_rows' => $removed,
            'affected_rows' => $removed,
        ], $input);
    }

    /**
     * @param  list<array<string, mixed>>  $records
     * @param  list<string>  $headers
     * @param  array<string, mixed>  $input
     * @return array{records: list<array<string, mixed>>, headers: list<string>, log: array<string, mixed>, summary: array<string, mixed>}
     */
    private function replaceValues(array $records, array $headers, array $input): array
    {
        $column = (string) ($input['column'] ?? '');
        $search = (string) ($input['search_value'] ?? $input['search'] ?? '');
        $replacement = (string) ($input['replacement_value'] ?? $input['replacement'] ?? $input['value'] ?? '');
        $caseSensitive = filter_var($input['case_sensitive'] ?? true, FILTER_VALIDATE_BOOL);

        $this->ensureColumnExists($records, $headers, $column);

        if ($search === '') {
            throw new \InvalidArgumentException('Enter the value to replace.');
        }

        $changed = 0;

        foreach ($records as &$record) {
            $value = $record[$column] ?? null;

            if ($this->isBlank($value)) {
                continue;
            }

            $matches = $caseSensitive
                ? (string) $value === $search
                : mb_strtolower((string) $value) === mb_strtolower($search);

            if ($matches) {
                $record[$column] = $replacement;
                $changed++;
            }
        }

        unset($record);

        return $this->result($records, $headers, 'replace_values', [
            'column' => $column,
            'search_value' => $search,
            'replacement_value' => $replacement,
            'replaced_cells' => $changed,
            'affected_cells' => $changed,
        ], $input);
    }

    /**
     * @param  list<array<string, mixed>>  $records
     * @param  list<string>  $headers
     * @param  array<string, mixed>  $input
     * @return array{records: list<array<string, mixed>>, headers: list<string>, log: array<string, mixed>, summary: array<string, mixed>}
     */
    private function removePattern(array $records, array $headers, array $input): array
    {
        $column = (string) ($input['column'] ?? '');
        $pattern = (string) ($input['pattern'] ?? '');
        $replacement = (string) ($input['replacement'] ?? '');

        $this->ensureColumnExists($records, $headers, $column);
        $regex = $this->regex($pattern);
        $changed = 0;

        foreach ($records as &$record) {
            $value = $record[$column] ?? null;

            if ($this->isBlank($value)) {
                continue;
            }

            $updated = preg_replace($regex, $replacement, (string) $value);

            if ($updated === null) {
                throw new \InvalidArgumentException('The pattern is not a valid regular expression.');
            }

            if ($updated !== (string) $value) {
                $record[$column] = $updated;
                $changed++;
            }
        }

        unset($record);

        return $this->result($records, $headers, 'remove_pattern', [
            'column' => $column,
            'pattern' => $pattern,
            'pattern_removed_cells' => $changed,
            'affected_cells' => $changed,
        ], $input);
    }

    /**
     * @param  list<array<string, mixed>>  $records
     * @param  list<string>  $headers
     * @param  array<string, mixed>  $input
     * @return array{records: list<array<string, mixed>>, headers: list<string>, log: array<string, mixed>, summary: array<string, mixed>}
     */
    private function extractNumber(array $records, array $headers, array $input): array
    {
        $column = (string) ($input['column'] ?? '');

        $this->ensureColumnExists($records, $headers, $column);
        $changed = 0;

        foreach ($records as &$record) {
            $value = $record[$column] ?? null;

            if ($this->isBlank($value)) {
                continue;
            }

            if (preg_match('/-?\d[\d,]*(?:\.\d+)?/', (string) $value, $matches) !== 1) {
                continue;
            }

            $number = str_replace(',', '', $matches[0]);

            if ($number !== (string) $value) {
                $record[$column] = $number;
                $changed++;
            }
        }

        unset($record);

        return $this->result($records, $headers, 'extract_number', [
            'column' => $column,
            'extracted_cells' => $changed,
            'affected_cells' => $changed,
        ], $input);
    }

    /**
     * @param  list<array<string, mixed>>  $records
     * @param  list<string>  $headers
     * @param  array<string, mixed>  $input
     * @return array{records: list<array<string, mixed>>, headers: list<string>, log: array<string, mixed>, summary: array<string, mixed>}
     */
    private function splitColumn(array $records, array $headers, array $input): array
    {
        $column = (string) ($input['column'] ?? '');
        $delimiter = (string) ($input['delimiter'] ?? ',');
        $newColumns = $this->stringList($input['new_columns'] ?? $input['new_column_names'] ?? '');

        $this->ensureColumnExists($records, $headers, $column);

        if ($delimiter === '') {
            throw new \InvalidArgumentException('Enter a delimiter for splitting the column.');
        }

        if ($newColumns === []) {
            $newColumns = [$column.' Part 1', $column.' Part 2'];
        }

        $this->ensureNewColumnsAvailable($headers, $newColumns);
        $changed = 0;

        foreach ($records as &$record) {
            $parts = array_map('trim', explode($delimiter, (string) ($record[$column] ?? '')));

            foreach ($newColumns as $index => $newColumn) {
                $record[$newColumn] = $parts[$index] ?? '';
            }

            if (count($parts) > 1) {
                $changed++;
            }
        }

        unset($record);

        $headers = array_values(array_unique(array_merge($headers, $newColumns)));

        return $this->result($records, $headers, 'split_column', [
            'column' => $column,
            'delimiter' => $delimiter,
            'new_columns' => implode(', ', $newColumns),
            'split_rows' => $changed,
            'affected_rows' => $changed,
        ], $input);
    }

    /**
     * @param  list<array<string, mixed>>  $records
     * @param  list<string>  $headers
     * @param  array<string, mixed>  $input
     * @return array{records: list<array<string, mixed>>, headers: list<string>, log: array<string, mixed>, summary: array<string, mixed>}
     */
    private function parseList(array $records, array $headers, array $input): array
    {
        $column = (string) ($input['column'] ?? '');
        $delimiter = (string) ($input['delimiter'] ?? ';');
        $outputDelimiter = (string) ($input['output_delimiter'] ?? ', ');

        $this->ensureColumnExists($records, $headers, $column);
        $changed = 0;

        foreach ($records as &$record) {
            $value = $record[$column] ?? null;

            if ($this->isBlank($value)) {
                continue;
            }

            $parts = array_values(array_filter(array_map('trim', explode($delimiter, (string) $value)), fn (string $part): bool => $part !== ''));
            $updated = implode($outputDelimiter, $parts);

            if ($updated !== (string) $value) {
                $record[$column] = $updated;
                $changed++;
            }
        }

        unset($record);

        return $this->result($records, $headers, 'parse_list', [
            'column' => $column,
            'delimiter' => $delimiter,
            'parsed_cells' => $changed,
            'affected_cells' => $changed,
        ], $input);
    }

    /**
     * @param  list<array<string, mixed>>  $records
     * @param  list<string>  $headers
     * @param  array<string, mixed>  $input
     * @return array{records: list<array<string, mixed>>, headers: list<string>, log: array<string, mixed>, summary: array<string, mixed>}
     */
    private function renameColumn(array $records, array $headers, array $input): array
    {
        $column = (string) ($input['column'] ?? '');
        $newColumn = trim((string) ($input['new_column'] ?? $input['new_name'] ?? ''));

        $this->ensureColumnExists($records, $headers, $column);

        if ($newColumn === '') {
            throw new \InvalidArgumentException('Enter the new column name.');
        }

        if (in_array($newColumn, $headers, true)) {
            throw new \InvalidArgumentException('The new column name already exists.');
        }

        foreach ($records as &$record) {
            $record[$newColumn] = $record[$column] ?? null;
            unset($record[$column]);
        }

        unset($record);

        $headers = array_map(fn (string $header): string => $header === $column ? $newColumn : $header, $headers);

        return $this->result($records, $headers, 'rename_column', [
            'column' => $column,
            'new_column' => $newColumn,
            'renamed_columns' => 1,
        ], $input);
    }

    /**
     * @param  list<array<string, mixed>>  $records
     * @param  list<string>  $headers
     * @param  array<string, mixed>  $input
     * @return array{records: list<array<string, mixed>>, headers: list<string>, log: array<string, mixed>, summary: array<string, mixed>}
     */
    private function removeColumn(array $records, array $headers, array $input): array
    {
        $column = (string) ($input['column'] ?? '');

        $this->ensureColumnExists($records, $headers, $column);

        foreach ($records as &$record) {
            unset($record[$column]);
        }

        unset($record);

        $headers = array_values(array_filter($headers, fn (string $header): bool => $header !== $column));

        return $this->result($records, $headers, 'remove_column', [
            'column' => $column,
            'removed_columns' => 1,
        ], $input);
    }

    /**
     * @param  list<array<string, mixed>>  $records
     * @param  list<string>  $headers
     * @param  array<string, mixed>  $input
     * @return array{records: list<array<string, mixed>>, headers: list<string>, log: array<string, mixed>, summary: array<string, mixed>}
     */
    private function mergeColumns(array $records, array $headers, array $input): array
    {
        $columns = $this->stringList($input['columns'] ?? '');
        $newColumn = trim((string) ($input['new_column'] ?? ''));
        $separator = (string) ($input['separator'] ?? ' ');

        if ($columns === []) {
            $columns = array_filter([(string) ($input['column'] ?? ''), (string) ($input['second_column'] ?? '')]);
        }

        foreach ($columns as $column) {
            $this->ensureColumnExists($records, $headers, $column);
        }

        if ($newColumn === '') {
            throw new \InvalidArgumentException('Enter a new column name for the merged values.');
        }

        if (in_array($newColumn, $headers, true)) {
            throw new \InvalidArgumentException('The merged column name already exists.');
        }

        foreach ($records as &$record) {
            $parts = array_values(array_filter(array_map(fn (string $column): string => trim((string) ($record[$column] ?? '')), $columns), fn (string $part): bool => $part !== ''));
            $record[$newColumn] = implode($separator, $parts);
        }

        unset($record);
        $headers[] = $newColumn;

        return $this->result($records, $headers, 'merge_columns', [
            'columns' => implode(', ', $columns),
            'new_column' => $newColumn,
            'merged_rows' => count($records),
            'affected_rows' => count($records),
        ], $input);
    }

    /**
     * @param  list<array<string, mixed>>  $records
     * @param  list<string>  $headers
     * @param  array<string, mixed>  $input
     * @return array{records: list<array<string, mixed>>, headers: list<string>, log: array<string, mixed>, summary: array<string, mixed>}
     */
    private function numericRangeFilter(array $records, array $headers, array $input): array
    {
        $column = (string) ($input['column'] ?? '');
        $min = $input['min'] ?? null;
        $max = $input['max'] ?? null;

        $this->ensureColumnExists($records, $headers, $column);

        if ($this->isBlank($min) && $this->isBlank($max)) {
            throw new \InvalidArgumentException('Enter at least one numeric range boundary.');
        }

        $cleaned = [];
        $removed = 0;

        foreach ($records as $record) {
            $value = $record[$column] ?? null;
            $number = $this->numericValue($value);
            $isInvalid = $number === null
                || (! $this->isBlank($min) && $number < (float) $min)
                || (! $this->isBlank($max) && $number > (float) $max);

            if ($isInvalid) {
                $removed++;

                continue;
            }

            $cleaned[] = $record;
        }

        return $this->result($cleaned, $headers, 'numeric_range_filter', [
            'column' => $column,
            'min' => $min,
            'max' => $max,
            'removed_rows' => $removed,
            'affected_rows' => $removed,
        ], $input);
    }

    /**
     * @param  list<array<string, mixed>>  $records
     * @param  list<string>  $headers
     * @param  array<string, mixed>  $input
     * @return array{records: list<array<string, mixed>>, headers: list<string>, log: array<string, mixed>, summary: array<string, mixed>}
     */
    private function dateFormatConvert(array $records, array $headers, array $input): array
    {
        $column = (string) ($input['column'] ?? '');
        $format = (string) ($input['date_format'] ?? 'Y-m-d');

        $this->ensureColumnExists($records, $headers, $column);
        $changed = 0;

        foreach ($records as &$record) {
            $value = $record[$column] ?? null;

            if ($this->isBlank($value)) {
                continue;
            }

            $timestamp = strtotime((string) $value);

            if ($timestamp === false) {
                continue;
            }

            $updated = date($format, $timestamp);

            if ($updated !== (string) $value) {
                $record[$column] = $updated;
                $changed++;
            }
        }

        unset($record);

        return $this->result($records, $headers, 'date_format_convert', [
            'column' => $column,
            'date_format' => $format,
            'converted_cells' => $changed,
            'affected_cells' => $changed,
        ], $input);
    }

    /**
     * @param  list<array<string, mixed>>  $records
     * @param  list<string>  $headers
     * @param  array<string, mixed>  $input
     * @return array{records: list<array<string, mixed>>, headers: list<string>, log: array<string, mixed>, summary: array<string, mixed>}
     */
    private function removeSpecialCharacters(array $records, array $headers, array $input): array
    {
        $column = (string) ($input['column'] ?? '');
        $pattern = '/[^\p{L}\p{N}\s.,_-]/u';

        $this->ensureColumnExists($records, $headers, $column);
        $changed = 0;

        foreach ($records as &$record) {
            $value = $record[$column] ?? null;

            if ($this->isBlank($value)) {
                continue;
            }

            $updated = trim((string) preg_replace($pattern, '', (string) $value));

            if ($updated !== (string) $value) {
                $record[$column] = $updated;
                $changed++;
            }
        }

        unset($record);

        return $this->result($records, $headers, 'remove_special_characters', [
            'column' => $column,
            'cleaned_cells' => $changed,
            'affected_cells' => $changed,
        ], $input);
    }

    private function convertValue(mixed $value, string $targetType): mixed
    {
        return match ($targetType) {
            'text' => (string) $value,
            'numeric' => $this->numericValue($value) ?? throw new \InvalidArgumentException('Selected column contains values that cannot be converted to numeric.'),
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
            'numeric' => $this->numericValue($value) !== null,
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
        $numbers = [];

        foreach ($values as $value) {
            $number = $this->numericValue($value);

            if ($number === null) {
                return [];
            }

            $numbers[] = $number;
        }

        return $numbers;
    }

    private function numericValue(mixed $value): ?float
    {
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        $normalized = str_replace([',', '$', '€', '£', '%'], '', trim((string) $value));

        if (! is_numeric($normalized)) {
            return null;
        }

        return (float) $normalized;
    }

    /**
     * Push the current cleaned_records and headers onto the snapshots stack before a cleaning operation.
     *
     * @return list<array<string, mixed>>
     */
    public function pushSnapshot(Dataset $dataset): array
    {
        $snapshots = $dataset->cleaning_snapshots ?? [];
        $currentRecords = $dataset->cleaned_records ?? [];

        array_unshift($snapshots, [
            'records' => $currentRecords,
            'headers' => $dataset->headers ?? [],
        ]);

        if (count($snapshots) > 10) {
            $snapshots = array_slice($snapshots, 0, 10);
        }

        return $snapshots;
    }

    /**
     * @param  list<array<string, mixed>>  $records
     * @param  list<string>  $headers
     * @param  array<string, mixed>  $summary
     * @param  array<string, mixed>  $input
     * @return array{records: list<array<string, mixed>>, headers: list<string>, log: array<string, mixed>, summary: array<string, mixed>}
     */
    private function result(array $records, array $headers, string $operation, array $summary, array $input): array
    {
        $parameters = $input;
        unset($parameters['operation']);

        return [
            'records' => $records,
            'headers' => array_values($headers),
            'log' => [
                'operation' => $operation,
                'column' => $input['column'] ?? null,
                'parameters' => $parameters,
                'affected_rows' => $summary['affected_rows'] ?? $summary['removed_rows'] ?? null,
                'affected_cells' => $summary['affected_cells'] ?? null,
                'source' => $input['source'] ?? 'manual',
                'summary' => $summary,
                'applied_at' => now()->toISOString(),
            ],
            'summary' => $summary,
        ];
    }

    private function isBlank(mixed $value): bool
    {
        if ($value === null) {
            return true;
        }

        if (! is_string($value)) {
            return false;
        }

        $normalized = mb_strtolower(trim($value));

        return in_array($normalized, ['', 'n/a', 'na', 'null', 'none', 'missing', 'unknown', '-', '--', '[]'], true);
    }

    /**
     * @param  list<array<string, mixed>>  $records
     * @param  list<string>  $headers
     */
    private function ensureColumnExists(array $records, array $headers, string $column): void
    {
        if ($column === '') {
            throw new \InvalidArgumentException('Select a column for this cleaning action.');
        }

        if (in_array($column, $headers, true)) {
            return;
        }

        if ($records === []) {
            throw new \InvalidArgumentException('Selected column was not found in this dataset.');
        }

        if (! array_key_exists($column, $records[0])) {
            throw new \InvalidArgumentException('Selected column was not found in this dataset.');
        }
    }

    /**
     * @param  list<string>  $headers
     * @param  list<string>  $newColumns
     */
    private function ensureNewColumnsAvailable(array $headers, array $newColumns): void
    {
        foreach ($newColumns as $newColumn) {
            if ($newColumn === '') {
                throw new \InvalidArgumentException('New column names cannot be blank.');
            }

            if (in_array($newColumn, $headers, true)) {
                throw new \InvalidArgumentException('One of the new column names already exists.');
            }
        }
    }

    private function regex(string $pattern): string
    {
        if ($pattern === '') {
            throw new \InvalidArgumentException('Enter a pattern to remove.');
        }

        if (str_starts_with($pattern, '/') && str_ends_with($pattern, '/')) {
            return $pattern;
        }

        return '~'.str_replace('~', '\\~', $pattern).'~u';
    }

    /**
     * @return list<string>
     */
    private function stringList(mixed $value): array
    {
        if (is_array($value)) {
            return array_values(array_filter(array_map(fn (mixed $item): string => trim((string) $item), $value), fn (string $item): bool => $item !== ''));
        }

        return array_values(array_filter(array_map('trim', explode(',', (string) $value)), fn (string $item): bool => $item !== ''));
    }
}
