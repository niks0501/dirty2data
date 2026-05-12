<?php

namespace App\Http\Requests\Datasets;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;

class StoreDatasetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'dataset_file' => [
                'required',
                File::types(['csv', 'xlsx', 'xls'])
                    ->max('50mb'),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'dataset_file.required' => 'Please select a file to upload.',
            'dataset_file.mimes' => 'Invalid file type. Please upload a CSV or Excel file (.csv, .xlsx, .xls).',
            'dataset_file.max' => 'The file is too large. Maximum size is 50MB.',
        ];
    }
}
