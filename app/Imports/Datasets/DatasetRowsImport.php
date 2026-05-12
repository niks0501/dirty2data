<?php

namespace App\Imports\Datasets;

use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\ToArray;
use Maatwebsite\Excel\Concerns\WithCalculatedFormulas;

class DatasetRowsImport implements SkipsEmptyRows, ToArray, WithCalculatedFormulas
{
    /**
     * @var array<int, array<int, mixed>>
     */
    private array $rows = [];

    /**
     * @param  array<int, array<int, mixed>>  $array
     */
    public function array(array $array): void
    {
        $this->rows = $array;
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    public function rows(): array
    {
        return $this->rows;
    }
}
