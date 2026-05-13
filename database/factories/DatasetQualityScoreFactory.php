<?php

namespace Database\Factories;

use App\Models\Dataset;
use App\Models\DatasetQualityScore;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DatasetQualityScore>
 */
class DatasetQualityScoreFactory extends Factory
{
    protected $model = DatasetQualityScore::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'dataset_id' => Dataset::factory(),
            'score_type' => 'before',
            'quality_score' => 85,
            'status' => 'Good',
            'completeness_score' => 90.00,
            'uniqueness_score' => 95.00,
            'validity_score' => 88.00,
            'consistency_score' => 82.00,
            'type_accuracy_score' => 85.00,
            'missing_values' => 5,
            'duplicate_rows' => 2,
            'invalid_values' => 1,
            'inconsistent_columns' => 1,
            'type_issue_columns' => 1,
        ];
    }
}
