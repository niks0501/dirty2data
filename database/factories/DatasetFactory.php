<?php

namespace Database\Factories;

use App\Models\Dataset;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Dataset>
 */
class DatasetFactory extends Factory
{
    protected $model = Dataset::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $headers = ['Name', 'Age', 'City'];
        $records = [
            ['Name' => 'Ada', 'Age' => '34', 'City' => 'London'],
            ['Name' => 'Grace', 'Age' => '29', 'City' => 'New York'],
        ];

        return [
            'uploaded_by_id' => User::factory(),
            'original_name' => 'sample.csv',
            'disk_path' => 'datasets/sample.csv',
            'mime_type' => 'text/csv',
            'extension' => 'csv',
            'size_bytes' => 1024,
            'row_count' => count($records),
            'column_count' => count($headers),
            'headers' => $headers,
            'original_records' => $records,
            'cleaned_records' => $records,
            'preview' => [
                'headers' => $headers,
                'sample_rows' => $records,
                'row_count' => count($records),
                'column_count' => count($headers),
            ],
            'profile' => null,
            'cleaning_log' => [],
        ];
    }
}
