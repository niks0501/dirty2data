<?php

namespace App\Services\Datasets;

use App\Imports\Datasets\DatasetRowsImport;
use Maatwebsite\Excel\Excel as ExcelReader;
use Maatwebsite\Excel\Facades\Excel;
use Throwable;

class DatasetPreviewParser
{
    private const int MAX_SAMPLE_ROWS = 15;

    /**
     * Parse a CSV or Excel file into normalized records and preview metadata.
     *
     * @return array{headers: list<string>, records: list<array<string, mixed>>, sample_rows: list<array<string, mixed>>, row_count: int, column_count: int}
     */
    public function parse(string $filePath, ?string $extension = null): array
    {
        if (! is_file($filePath) || ! is_readable($filePath)) {
            throw new \RuntimeException('The uploaded file could not be found or read.');
        }

        $readerType = $this->readerType($extension ?? pathinfo($filePath, PATHINFO_EXTENSION));
        $import = new DatasetRowsImport;

        try {
            Excel::import($import, $filePath, null, $readerType);
        } catch (Throwable $e) {
            throw new \RuntimeException(
                'Unable to read the file. It may be corrupt or in an unsupported format.',
                0,
                $e,
            );
        }

        $rows = $this->trimTrailingEmptyRows($import->rows());

        if ($rows === []) {
            throw new \InvalidArgumentException('The dataset is empty. Please upload a file with headers and data.');
        }

        $headers = $this->normalizeHeaders(array_shift($rows) ?? []);
        $this->validateHeaders($headers);

        $records = $this->normalizeRecords($headers, $rows);

        return [
            'headers' => $headers,
            'records' => $records,
            'sample_rows' => array_slice($records, 0, self::MAX_SAMPLE_ROWS),
            'row_count' => count($records),
            'column_count' => count($headers),
        ];
    }

    private function readerType(string $extension): string
    {
        return match (strtolower($extension)) {
            'csv' => ExcelReader::CSV,
            'xls' => ExcelReader::XLS,
            'xlsx' => ExcelReader::XLSX,
            default => throw new \InvalidArgumentException('Invalid file format. Please upload a CSV or Excel file.'),
        };
    }

    /**
     * @param  array<int, array<int, mixed>>  $rows
     * @return array<int, array<int, mixed>>
     */
    private function trimTrailingEmptyRows(array $rows): array
    {
        return array_values(array_filter($rows, fn (array $row): bool => ! $this->isEmptyRow($row)));
    }

    /**
     * @param  array<int, mixed>  $row
     */
    private function isEmptyRow(array $row): bool
    {
        foreach ($row as $value) {
            if (! $this->isBlank($value)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<int, mixed>  $headerRow
     * @return list<string>
     */
    private function normalizeHeaders(array $headerRow): array
    {
        return array_values(array_map(fn (mixed $value): string => trim((string) $value), $headerRow));
    }

    /**
     * @param  list<string>  $headers
     */
    private function validateHeaders(array $headers): void
    {
        if ($headers === []) {
            throw new \InvalidArgumentException('The dataset has no column headers.');
        }

        $seen = [];
        $duplicates = [];

        foreach ($headers as $header) {
            if ($header === '') {
                throw new \InvalidArgumentException('The dataset has blank column headers.');
            }

            $key = mb_strtolower($header);

            if (isset($seen[$key])) {
                $duplicates[] = $header;
            }

            $seen[$key] = true;
        }

        if ($duplicates !== []) {
            throw new \InvalidArgumentException('The dataset has duplicate column headers: '.implode(', ', $duplicates));
        }
    }

    /**
     * @param  list<string>  $headers
     * @param  array<int, array<int, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function normalizeRecords(array $headers, array $rows): array
    {
        $records = [];
        $columnCount = count($headers);

        foreach ($rows as $row) {
            $paddedRow = array_pad(array_slice($row, 0, $columnCount), $columnCount, null);
            $record = [];

            foreach ($headers as $index => $header) {
                $value = $paddedRow[$index] ?? null;
                $record[$header] = is_string($value) ? trim($value) : $value;
            }

            $records[] = $record;
        }

        return $records;
    }

    private function isBlank(mixed $value): bool
    {
        return $value === null || (is_string($value) && trim($value) === '');
    }
}
