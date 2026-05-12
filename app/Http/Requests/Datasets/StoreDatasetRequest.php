<?php

namespace App\Http\Requests\Datasets;

use App\Services\Datasets\DatasetPreviewParser;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;
use Illuminate\Validation\Validator;

class StoreDatasetRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'dataset_file' => [
                'required',
                File::types(['csv', 'xlsx', 'xls'])
                    ->max('10mb'),
            ],
        ];
    }

    /**
     * Configure the "after" validation callables for the request.
     *
     * After basic file validation passes, attempt to parse the file
     * to verify it is readable and has valid structure.
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $file = $this->file('dataset_file');

                if (! $file) {
                    return;
                }

                try {
                    $parser = new DatasetPreviewParser;
                    $parser->parse($file->getRealPath(), $file->getClientOriginalExtension());
                } catch (\InvalidArgumentException $e) {
                    $validator->errors()->add(
                        'dataset_file',
                        $e->getMessage(),
                    );
                } catch (\RuntimeException $e) {
                    $validator->errors()->add(
                        'dataset_file',
                        $e->getMessage(),
                    );
                }
            },
        ];
    }

    /**
     * Get the custom error messages for validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'dataset_file.required' => 'Please select a file to upload.',
            'dataset_file.mimes' => 'Invalid file type. Please upload a CSV or Excel file (.csv, .xlsx, .xls).',
            'dataset_file.max' => 'The file is too large. Maximum size is 10MB.',
        ];
    }
}
