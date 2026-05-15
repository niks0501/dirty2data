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

        $normalized = $this->normalizeBlankToken($value);

        if ($normalized === '' || preg_match('/^-+$/', $normalized) === 1) {
            return true;
        }

        return in_array($normalized, [
            'n/a',
            '#n/a',
            'na',
            'null',
            'nil',
            'none',
            'missing',
            'unknown',
            'blank',
            'notavailable',
            'notapplicable',
            '[]',
        ], true);
    }

    public function isBooleanLike(mixed $value): bool
    {
        if (is_bool($value)) {
            return true;
        }

        if (is_int($value) || is_float($value)) {
            return in_array((string) $value, ['1', '0'], true);
        }

        return is_string($value) && in_array(mb_strtolower(trim($value)), ['true', 'false', 'yes', 'no', 'y', 'n', 't', 'f', 'on', 'off', '1', '0'], true);
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

    private function normalizeBlankToken(string $value): string
    {
        $normalized = mb_strtolower(trim($value));
        $normalized = trim($normalized, " \t\n\r\0\x0B\"'`.,;:(){}");

        return str_replace([' ', '.', '_'], '', $normalized);
    }
}
