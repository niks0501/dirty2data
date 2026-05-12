<?php

namespace App\Http\Requests\Datasets;

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
            'operation' => ['required', Rule::in(['remove_duplicates', 'fill_missing', 'convert_type', 'standardize_text', 'filter_invalid'])],
            'column' => ['nullable', 'string'],
            'method' => ['nullable', Rule::in(['mean', 'median', 'mode', 'custom', 'blank'])],
            'value' => ['nullable', 'string'],
            'target_type' => ['nullable', Rule::in(['numeric', 'text', 'date', 'boolean', 'non_blank'])],
            'text_format' => ['nullable', Rule::in(['trim', 'lowercase', 'uppercase', 'title'])],
        ];
    }

    public function messages(): array
    {
        return [
            'operation.required' => 'Select a cleaning action first.',
        ];
    }
}
