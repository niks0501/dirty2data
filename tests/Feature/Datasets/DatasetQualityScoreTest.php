<?php

use App\Models\Dataset;
use App\Models\DatasetQualityScore;
use App\Models\User;

test('quality score belongs to dataset', function () {
    $user = User::factory()->create();
    $dataset = Dataset::factory()->create(['uploaded_by_id' => $user->id]);

    $score = DatasetQualityScore::factory()->create([
        'dataset_id' => $dataset->id,
    ]);

    expect($score->dataset)->toBeInstanceOf(Dataset::class)
        ->and($score->dataset->id)->toBe($dataset->id);
});

test('quality score stores before and after types', function () {
    $user = User::factory()->create();
    $dataset = Dataset::factory()->create(['uploaded_by_id' => $user->id]);

    $before = DatasetQualityScore::factory()->create([
        'dataset_id' => $dataset->id,
        'score_type' => 'before',
    ]);

    $after = DatasetQualityScore::factory()->create([
        'dataset_id' => $dataset->id,
        'score_type' => 'after',
    ]);

    expect($before->score_type)->toBe('before')
        ->and($after->score_type)->toBe('after');
});

test('quality score values are within 0 to 100', function () {
    $user = User::factory()->create();
    $dataset = Dataset::factory()->create(['uploaded_by_id' => $user->id]);

    $score = DatasetQualityScore::factory()->create([
        'dataset_id' => $dataset->id,
        'quality_score' => 78,
        'completeness_score' => 82.00,
        'uniqueness_score' => 95.00,
        'validity_score' => 76.00,
        'consistency_score' => 70.00,
        'type_accuracy_score' => 85.00,
    ]);

    expect($score->quality_score)->toBeGreaterThanOrEqual(0)
        ->and($score->quality_score)->toBeLessThanOrEqual(100)
        ->and((float) $score->completeness_score)->toBeGreaterThanOrEqual(0)
        ->and((float) $score->completeness_score)->toBeLessThanOrEqual(100);
});

test('dataset model has latestBeforeQualityScore relation', function () {
    $user = User::factory()->create();
    $dataset = Dataset::factory()->create(['uploaded_by_id' => $user->id]);

    // Create a "before" score and a different dataset's "before" score
    DatasetQualityScore::factory()->create([
        'dataset_id' => $dataset->id,
        'score_type' => 'before',
        'quality_score' => 78,
    ]);

    $beforeScore = $dataset->latestBeforeQualityScore();

    expect($beforeScore)->not->toBeNull()
        ->and($beforeScore->quality_score)->toBe(78)
        ->and($beforeScore->score_type)->toBe('before');

    // A dataset without a before score returns null
    $emptyDataset = Dataset::factory()->create(['uploaded_by_id' => $user->id]);

    expect($emptyDataset->latestBeforeQualityScore())->toBeNull();
});

test('status is computed from quality score thresholds', function (int $score, string $expected) {
    $user = User::factory()->create();
    $dataset = Dataset::factory()->create(['uploaded_by_id' => $user->id]);

    $qs = DatasetQualityScore::factory()->create([
        'dataset_id' => $dataset->id,
        'quality_score' => $score,
        'status' => $expected,
    ]);

    expect($qs->status)->toBe($expected);
})->with([
    [95, 'Excellent'],
    [80, 'Good'],
    [65, 'Fair'],
    [50, 'Poor'],
    [20, 'Critical'],
]);
