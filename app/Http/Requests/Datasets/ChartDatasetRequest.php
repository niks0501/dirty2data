<?php

namespace App\Http\Requests\Datasets;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ChartDatasetRequest extends FormRequest
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
            'chart_type' => ['required', Rule::in(['bar', 'line', 'pie', 'histogram', 'scatter'])],
            'x_column' => ['required', 'string'],
            'y_column' => ['nullable', 'string'],
        ];
    }
}
