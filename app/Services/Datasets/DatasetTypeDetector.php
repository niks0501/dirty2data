<?php

namespace App\Services\Datasets;

class DatasetTypeDetector
{
    /**
     * @param  list<mixed>  $values
     */
    public function detect(array $values): string
    {
        $nonBlank = array_values(array_filter(
            $values,
            fn (mixed $value): bool => ! $this->isBlank($value),
        ));

        if ($nonBlank === []) {
            return 'empty';
        }

        if ($this->all($nonBlank, fn (mixed $value): bool => $this->isBooleanLike($value))) {
            return 'boolean';
        }

        if ($this->all($nonBlank, fn (mixed $value): bool => is_numeric($value))) {
            return 'numeric';
        }

        if ($this->all($nonBlank, fn (mixed $value): bool => $this->isDateLike($value))) {
            return 'date';
        }

        return 'text';
    }

    public function isBlank(mixed $value): bool
    {
        if ($value === null) {
            return true;
        }

        if (! is_string($value)) {
            return false;
        }

        return in_array(mb_strtolower(trim($value)), ['', 'n/a', 'na', 'null', 'none', 'missing', 'unknown', '-', '--', '[]'], true);
    }

    public function isBooleanLike(mixed $value): bool
    {
        if (is_bool($value)) {
            return true;
        }

        return is_string($value) && in_array(mb_strtolower(trim($value)), ['true', 'false', 'yes', 'no', '1', '0'], true);
    }

    public function isDateLike(mixed $value): bool
    {
        if (! is_string($value) || is_numeric($value)) {
            return false;
        }

        return strtotime($value) !== false;
    }

    /**
     * @param  list<mixed>  $values
     */
    private function all(array $values, callable $callback): bool
    {
        foreach ($values as $value) {
            if (! $callback($value)) {
                return false;
            }
        }

        return true;
    }
}
