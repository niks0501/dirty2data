<?php

namespace App\Services\Datasets;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Exception;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Csv as CsvReader;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class DatasetPreviewParser
{
    private const int MAX_SAMPLE_ROWS = 10;

    private const array COMMON_DELIMITERS = [',', ';', "\t"];

    /**
     * Parse a CSV or Excel file and return preview data.
     *
     * @return array{headers: string[], sample_rows: array<array<mixed>>, row_count: int, column_count: int}
     *
     * @throws \InvalidArgumentException If the file is empty or has invalid headers.
     * @throws \RuntimeException If the file cannot be read or parsed.
     */
    public function parse(string $filePath): array
    {
        if (! file_exists($filePath)) {
            throw new \RuntimeException('The uploaded file could not be found.');
        }

        try {
            $spreadsheet = $this->loadSpreadsheet($filePath);
        } catch (Exception $e) {
            throw new \RuntimeException(
                'Unable to read the file. It may be corrupt or in an unsupported format.',
                0,
                $e
            );
        }

        $worksheet = $spreadsheet->getActiveSheet();

        $this->ensureNotEmpty($worksheet);

        $headers = $this->extractHeaders($worksheet);

        $this->validateHeaders($headers);

        ['row_count' => $rowCount, 'sample_rows' => $sampleRows] = $this->collectDataRows($worksheet);
        $columnCount = count($headers);

        return [
            'headers' => $headers,
            'sample_rows' => $sampleRows,
            'row_count' => $rowCount,
            'column_count' => $columnCount,
        ];
    }

    /**
     * Load a spreadsheet using the appropriate reader based on file extension.
     *
     * Falls back to IOFactory::load() for unknown extensions.
     */
    private function loadSpreadsheet(string $filePath): Spreadsheet
    {
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        if ($extension === 'csv') {
            return $this->loadCsv($filePath);
        }

        if ($extension === 'xlsx') {
            return $this->loadExcel($filePath, 'Xlsx');
        }

        if ($extension === 'xls') {
            return $this->loadExcel($filePath, 'Xls');
        }

        // Fallback: auto-detect the file type
        $spreadsheet = IOFactory::load($filePath);

        if (! ($spreadsheet instanceof Spreadsheet)) {
            throw new \RuntimeException('Unable to read the file. Unsupported format.');
        }

        return $spreadsheet;
    }

    /**
     * Load a CSV file with auto-detected encoding and delimiter.
     */
    private function loadCsv(string $filePath): Spreadsheet
    {
        $reader = new CsvReader;
        $reader->setInputEncoding(CsvReader::GUESS_ENCODING);
        $reader->setFallbackEncoding('UTF-8');

        $delimiter = $this->detectCsvDelimiter($filePath);
        $reader->setDelimiter($delimiter);

        return $reader->load($filePath);
    }

    /**
     * Load an Excel file (Xlsx or Xls) with read-data-only for memory efficiency.
     */
    private function loadExcel(string $filePath, string $type): Spreadsheet
    {
        $reader = IOFactory::createReader($type);
        $reader->setReadDataOnly(true);

        return $reader->load($filePath);
    }

    /**
     * Detect the delimiter used in a CSV file by sniffing the first few lines.
     *
     * Counts occurrences of common delimiters (comma, semicolon, tab) across
     * the first 5 non-empty lines and picks the one with the highest count.
     */
    private function detectCsvDelimiter(string $filePath): string
    {
        $handle = fopen($filePath, 'r');

        if (! $handle) {
            return ',';
        }

        $lines = [];
        $lineCount = 0;

        while (($line = fgets($handle)) !== false && $lineCount < 5) {
            $line = trim($line);

            if ($line !== '') {
                $lines[] = $line;
                $lineCount++;
            }
        }

        fclose($handle);

        if (empty($lines)) {
            return ',';
        }

        $bestDelimiter = ',';
        $bestCount = 0;

        foreach (self::COMMON_DELIMITERS as $delimiter) {
            $count = 0;

            foreach ($lines as $line) {
                $count += substr_count($line, $delimiter);
            }

            if ($count > $bestCount) {
                $bestCount = $count;
                $bestDelimiter = $delimiter;
            }
        }

        return $bestDelimiter;
    }

    /**
     * Ensure the spreadsheet is not empty.
     *
     * Checks the entire first row (not just A1) for any content
     * to avoid false positives when only the first cell is blank.
     *
     * @throws \InvalidArgumentException If the file has no data rows.
     */
    private function ensureNotEmpty(Worksheet $worksheet): void
    {
        $highestDataRow = $worksheet->getHighestDataRow();

        if ($highestDataRow < 1) {
            throw new \InvalidArgumentException('The dataset is empty. Please upload a file with data.');
        }

        // When only one row exists, check the full row for any content
        if ($highestDataRow === 1) {
            $highestDataColumn = $worksheet->getHighestDataColumn();
            $firstRow = $worksheet->rangeToArray(
                "A1:{$highestDataColumn}1",
                null,
                false,
                false
            )[0] ?? [];

            $hasContent = false;

            foreach ($firstRow as $cell) {
                if ($cell !== null && trim((string) $cell) !== '') {
                    $hasContent = true;

                    break;
                }
            }

            if (! $hasContent) {
                throw new \InvalidArgumentException('The dataset is empty. Please upload a file with data.');
            }
        }
    }

    /**
     * Extract column headers from the first row of the worksheet.
     *
     * @return string[] Trimmed header values.
     */
    private function extractHeaders(Worksheet $worksheet): array
    {
        $highestDataColumn = $worksheet->getHighestDataColumn();
        $headerRange = "A1:{$highestDataColumn}1";

        $headerRow = $worksheet->rangeToArray($headerRange, null, false, false)[0] ?? [];

        return array_map(function ($value): string {
            return trim((string) $value);
        }, $headerRow);
    }

    /**
     * Validate that headers are not blank and contain no duplicates.
     *
     * @param  string[]  $headers
     *
     * @throws \InvalidArgumentException If any header is blank or duplicates exist.
     */
    private function validateHeaders(array $headers): void
    {
        if (empty($headers)) {
            throw new \InvalidArgumentException('The dataset has no column headers.');
        }

        $lowerHeaders = array_map('strtolower', $headers);
        $seen = [];
        $originalDuplicates = [];

        foreach ($lowerHeaders as $index => $lower) {
            $original = $headers[$index];

            if ($original === '') {
                throw new \InvalidArgumentException('The dataset has blank column headers.');
            }

            if (isset($seen[$lower])) {
                $originalDuplicates[] = $original;
            } else {
                $seen[$lower] = true;
            }
        }

        if (! empty($originalDuplicates)) {
            throw new \InvalidArgumentException(
                'The dataset has duplicate column headers: '.implode(', ', $originalDuplicates)
            );
        }
    }

    /**
     * Collect data rows using a memory-efficient generator.
     *
     * Only stores the first 10 rows as sample data; counts total rows
     * by iterating through the generator.
     *
     * @return array{row_count: int, sample_rows: array<array<mixed>>}
     */
    private function collectDataRows(Worksheet $worksheet): array
    {
        $highestDataColumn = $worksheet->getHighestDataColumn();
        $highestDataRow = $worksheet->getHighestDataRow();
        $columnIndex = Coordinate::columnIndexFromString($highestDataColumn);

        // No data rows beyond the header
        if ($highestDataRow <= 1) {
            return [
                'row_count' => 0,
                'sample_rows' => [],
            ];
        }

        $range = "A2:{$highestDataColumn}{$highestDataRow}";

        $rowGenerator = $worksheet->rangeToArrayYieldRows($range, null, false, false);

        $sampleRows = [];
        $rowCount = 0;

        foreach ($rowGenerator as $row) {
            // Pad row to match column count in case of trailing empty cells
            $paddedRow = array_pad($row, $columnIndex, null);

            if ($rowCount < self::MAX_SAMPLE_ROWS) {
                $sampleRows[] = $paddedRow;
            }

            $rowCount++;
        }

        return [
            'row_count' => $rowCount,
            'sample_rows' => $sampleRows,
        ];
    }
}
