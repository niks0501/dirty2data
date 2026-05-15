<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;

class CleanedDatasetExport implements FromArray, WithHeadings, WithStrictNullComparison
{
    /**
     * @param  list<array<string, mixed>>  $records
     * @param  list<string>  $headers
     */
    public function __construct(
        private readonly array $records,
        private readonly array $headers,
    ) {}

    /**
     * @return list<string>
     */
    public function headings(): array
    {
        return $this->headers;
    }

    /**
     * @return list<list<mixed>>
     */
    public function array(): array
    {
        return array_map(
            fn (array $record): array => array_map(
                fn (string $header): mixed => $record[$header] ?? null,
                $this->headers,
            ),
            $this->records,
        );
    }
}
