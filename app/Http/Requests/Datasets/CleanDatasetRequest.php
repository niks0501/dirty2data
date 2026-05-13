<?php

namespace App\Http\Requests\Datasets;

use App\Services\Datasets\DatasetCleaner;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CleanDatasetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'operation' => ['required', Rule::in(DatasetCleaner::OPERATIONS)],
            'column' => ['nullable', 'string'],
            'method' => ['nullable', Rule::in(['mean', 'median', 'mode', 'custom', 'blank'])],
            'value' => ['nullable', 'string'],
            'target_type' => ['nullable', Rule::in(['numeric', 'text', 'date', 'boolean', 'non_blank'])],
            'text_format' => ['nullable', Rule::in(['trim', 'lowercase', 'uppercase', 'title'])],
            'search' => ['nullable', 'string'],
            'search_value' => ['nullable', 'string'],
            'replacement' => ['nullable', 'string'],
            'replacement_value' => ['nullable', 'string'],
            'case_sensitive' => ['nullable', 'boolean'],
            'pattern' => ['nullable', 'string', 'max:500'],
            'delimiter' => ['nullable', 'string', 'max:20'],
            'output_delimiter' => ['nullable', 'string', 'max:20'],
            'new_columns' => ['nullable'],
            'new_column_names' => ['nullable'],
            'new_column' => ['nullable', 'string', 'max:255'],
            'new_name' => ['nullable', 'string', 'max:255'],
            'columns' => ['nullable'],
            'second_column' => ['nullable', 'string'],
            'separator' => ['nullable', 'string', 'max:20'],
            'min' => ['nullable', 'numeric'],
            'max' => ['nullable', 'numeric'],
            'date_format' => ['nullable', 'string', 'max:40'],
        ];
    }

    public function messages(): array
    {
        return [
            'operation.required' => 'Select a cleaning action first.',
        ];
    }
}
