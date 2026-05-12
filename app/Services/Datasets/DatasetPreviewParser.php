<?php

namespace App\Services\Datasets;

use App\Imports\Datasets\DatasetRowsImport;
use Maatwebsite\Excel\Excel as ExcelReader;
use Maatwebsite\Excel\Facades\Excel;
use Throwable;

class DatasetPreviewParser
{
    private const int MAX_SAMPLE_ROWS = 15;

    private const int CHUNK_SIZE = 5000;

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

        $extension ??= pathinfo($filePath, PATHINFO_EXTENSION);
        $readerType = $this->readerType($extension);

        if ($readerType === ExcelReader::CSV) {
            return $this->parseCsv($filePath);
        }

        return $this->parseExcel($filePath, $readerType);
    }

    /**
     * Parse a file with progress reporting. CSV files are truly streamed,
     * Excel files report coarse progress after import and during normalization.
     *
     * @param  callable(int $rowsProcessed, int $estimatedTotal): void  $onProgress
     * @return array{headers: list<string>, records: list<array<string, mixed>>, sample_rows: list<array<string, mixed>>, row_count: int, column_count: int}
     */
    public function parseChunked(string $filePath, ?string $extension, callable $onProgress): array
    {
        if (! is_file($filePath) || ! is_readable($filePath)) {
            throw new \RuntimeException('The uploaded file could not be found or read.');
        }

        $extension ??= pathinfo($filePath, PATHINFO_EXTENSION);
        $readerType = $this->readerType($extension);

        if ($readerType === ExcelReader::CSV) {
            return $this->parseCsvChunked($filePath, $onProgress);
        }

        return $this->parseExcelChunked($filePath, $readerType, $onProgress);
    }

    /**
     * Count rows in a CSV file (excluding header) without loading full content.
     */
    public function countRows(string $filePath, ?string $extension = null): int
    {
        $extension ??= pathinfo($filePath, PATHINFO_EXTENSION);
        $readerType = $this->readerType($extension);

        if ($readerType === ExcelReader::CSV) {
            return $this->countCsvRows($filePath);
        }

        $import = new DatasetRowsImport;

        try {
            Excel::import($import, $filePath, null, $readerType);
        } catch (Throwable $e) {
            return 0;
        }

        return max(count($import->rows()) - 1, 0);
    }

    /**
     * Estimate row count from file size. Used as a fallback for quick progress estimation.
     */
    public function estimateRowCount(string $filePath): int
    {
        $size = filesize($filePath);

        if ($size === false || $size === 0) {
            return 0;
        }

        return (int) max(1, round($size / 150));
    }

    /**
     * Synchronous CSV parser (backward-compatible).
     */
    private function parseCsv(string $filePath): array
    {
        $handle = fopen($filePath, 'r');

        if (! $handle) {
            throw new \RuntimeException('Unable to open the CSV file for reading.');
        }

        $headerRow = fgetcsv($handle);

        if ($headerRow === false || $headerRow === null) {
            fclose($handle);

            throw new \InvalidArgumentException('The dataset is empty. Please upload a file with headers and data.');
        }

        $headers = $this->normalizeHeaders($headerRow);
        $this->validateHeaders($headers);
        $records = $this->readCsvRows($handle, $headers);

        fclose($handle);

        return $this->buildResult($headers, $records);
    }

    /**
     * Synchronous Excel parser (backward-compatible).
     */
    private function parseExcel(string $filePath, string $readerType): array
    {
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

        return $this->buildResult($headers, $records);
    }

    /**
     * Chunked CSV parser with progress callbacks.
     */
    private function parseCsvChunked(string $filePath, callable $onProgress): array
    {
        $estimated = $this->estimateRowCount($filePath);

        if ($estimated === 0) {
            $estimated = 1;
        }

        $handle = fopen($filePath, 'r');

        if (! $handle) {
            throw new \RuntimeException('Unable to open the CSV file for reading.');
        }

        $headerRow = fgetcsv($handle);

        if ($headerRow === false || $headerRow === null) {
            fclose($handle);

            throw new \InvalidArgumentException('The dataset is empty. Please upload a file with headers and data.');
        }

        $headers = $this->normalizeHeaders($headerRow);
        $this->validateHeaders($headers);
        $records = $this->readCsvRowsWithProgress($handle, $headers, $onProgress, $estimated);

        fclose($handle);

        $actual = count($records);
        $onProgress($actual, $actual);

        return $this->buildResult($headers, $records);
    }

    /**
     * Chunked Excel parser — imports all rows, then normalizes in chunks for progress.
     */
    private function parseExcelChunked(string $filePath, string $readerType, callable $onProgress): array
    {
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

        $totalRows = count($rows);

        if ($totalRows === 0) {
            $onProgress(0, 1);

            return $this->buildResult($headers, []);
        }

        $onProgress(0, $totalRows);

        $records = [];
        $columnCount = count($headers);

        foreach (array_chunk($rows, self::CHUNK_SIZE) as $chunk) {
            foreach ($chunk as $row) {
                $paddedRow = array_pad(array_slice($row, 0, $columnCount), $columnCount, null);
                $record = [];

                foreach ($headers as $index => $header) {
                    $value = $paddedRow[$index] ?? null;
                    $record[$header] = is_string($value) ? trim($value) : $value;
                }

                $records[] = $record;
            }

            $onProgress(count($records), $totalRows);
        }

        return $this->buildResult($headers, $records);
    }

    /**
     * Read CSV rows from an open handle (synchronous, no progress).
     *
     * @param  resource  $handle
     * @param  list<string>  $headers
     * @return list<array<string, mixed>>
     */
    private function readCsvRows($handle, array $headers): array
    {
        $records = [];
        $columnCount = count($headers);

        while (($row = fgetcsv($handle)) !== false) {
            if ($this->isEmptyRow($row)) {
                continue;
            }

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

    /**
     * Read CSV rows with progress callbacks each chunk.
     *
     * @param  resource  $handle
     * @param  list<string>  $headers
     * @param  callable(int $rowsProcessed, int $estimatedTotal): void  $onProgress
     * @return list<array<string, mixed>>
     */
    private function readCsvRowsWithProgress($handle, array $headers, callable $onProgress, int $estimated): array
    {
        $records = [];
        $columnCount = count($headers);

        while (($row = fgetcsv($handle)) !== false) {
            if ($this->isEmptyRow($row)) {
                continue;
            }

            $paddedRow = array_pad(array_slice($row, 0, $columnCount), $columnCount, null);
            $record = [];

            foreach ($headers as $index => $header) {
                $value = $paddedRow[$index] ?? null;
                $record[$header] = is_string($value) ? trim($value) : $value;
            }

            $records[] = $record;

            if (count($records) % self::CHUNK_SIZE === 0) {
                $onProgress(count($records), $estimated);
            }
        }

        return $records;
    }

    /**
     * Count CSV rows (excluding header) by streaming through the file.
     */
    private function countCsvRows(string $filePath): int
    {
        $handle = fopen($filePath, 'r');

        if (! $handle) {
            return 0;
        }

        fgetcsv($handle);

        $count = 0;

        while (fgetcsv($handle) !== false) {
            $count++;
        }

        fclose($handle);

        return $count;
    }

    /**
     * @param  list<string>  $headers
     * @param  list<array<string, mixed>>  $records
     * @return array{headers: list<string>, records: list<array<string, mixed>>, sample_rows: list<array<string, mixed>>, row_count: int, column_count: int}
     */
    private function buildResult(array $headers, array $records): array
    {
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
