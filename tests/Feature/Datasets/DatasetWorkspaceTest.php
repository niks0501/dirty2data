<?php

use App\Models\Dataset;
use App\Models\User;
use App\Services\Datasets\DatasetProfiler;
use Inertia\Testing\AssertableInertia as Assert;

test('cleaning preview does not mutate dataset records', function () {
    $user = User::factory()->create();
    $records = [
        ['Name' => 'Ada', 'Age' => '34'],
        ['Name' => 'Ada', 'Age' => '34'],
        ['Name' => 'Grace', 'Age' => null],
    ];
    $dataset = Dataset::factory()->create([
        'uploaded_by_id' => $user->id,
        'headers' => ['Name', 'Age'],
        'original_records' => $records,
        'cleaned_records' => $records,
    ]);

    $response = $this
        ->actingAs($user)
        ->postJson(route('datasets.clean.preview', ['dataset' => $dataset]), [
            'operation' => 'remove_duplicates',
        ]);

    $response->assertOk()
        ->assertJsonPath('preview.summary.removed_rows', 1)
        ->assertJsonPath('preview.will_change_dataset', true);

    $dataset->refresh();

    expect($dataset->original_records)->toBe($records)
        ->and($dataset->cleaned_records)->toBe($records);
});

test('cleaning apply mutates only cleaned records and refreshes profile', function () {
    $user = User::factory()->create();
    $records = [
        ['Name' => 'Ada', 'Age' => '34'],
        ['Name' => 'Ada', 'Age' => '34'],
        ['Name' => 'Grace', 'Age' => null],
    ];
    $dataset = Dataset::factory()->create([
        'uploaded_by_id' => $user->id,
        'headers' => ['Name', 'Age'],
        'row_count' => 3,
        'column_count' => 2,
        'original_records' => $records,
        'cleaned_records' => $records,
        'cleaning_log' => [],
    ]);

    $response = $this
        ->actingAs($user)
        ->post(route('datasets.clean', ['dataset' => $dataset]), [
            'operation' => 'remove_duplicates',
        ]);

    $response->assertRedirect(route('datasets.show', ['dataset' => $dataset]));

    $dataset->refresh();

    expect($dataset->original_records)->toBe($records)
        ->and($dataset->cleaned_records)->toHaveCount(2)
        ->and($dataset->profile['duplicate_count'])->toBe(0)
        ->and($dataset->cleaning_log)->toHaveCount(1);
});

test('dataset workspace exposes compact props recommendations and selected column profile', function () {
    $this->withoutVite();

    $user = User::factory()->create();
    $records = [
        ['City' => 'London', 'Sales' => '10'],
        ['City' => 'London', 'Sales' => '20'],
        ['City' => 'Manila', 'Sales' => '8'],
    ];
    $dataset = Dataset::factory()->create([
        'uploaded_by_id' => $user->id,
        'headers' => ['City', 'Sales'],
        'row_count' => 3,
        'column_count' => 2,
        'original_records' => $records,
        'cleaned_records' => $records,
        'profile' => app(DatasetProfiler::class)->profile($records, ['City', 'Sales']),
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('datasets.show', ['dataset' => $dataset, 'column' => 'Sales']));

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('datasets/show')
            ->has('dataset.previewRows', 3)
            ->has('dataset.chartRecommendations')
            ->where('dataset.selectedColumn', 'Sales')
            ->where('dataset.selectedColumnProfile.name', 'Sales')
            ->missing('dataset.original_records')
            ->missing('dataset.cleaned_records'),
        );
});

test('AI recommendation generation stores local heuristic suggestions when Gemini is disabled', function () {
    config(['ai.enabled' => false]);

    $user = User::factory()->create();
    $records = [
        ['Peak' => '7[2]', 'Revenue' => '$780000000'],
        ['Peak' => '1[4]', 'Revenue' => '$42000'],
    ];
    $dataset = Dataset::factory()->create([
        'uploaded_by_id' => $user->id,
        'headers' => ['Peak', 'Revenue'],
        'row_count' => 2,
        'column_count' => 2,
        'original_records' => $records,
        'cleaned_records' => $records,
        'profile' => app(DatasetProfiler::class)->profile($records, ['Peak', 'Revenue']),
    ]);

    $response = $this
        ->actingAs($user)
        ->postJson(route('datasets.cleaning.recommendations.generate', ['dataset' => $dataset]));

    $response->assertOk()
        ->assertJsonPath('provider', 'local_heuristic')
        ->assertJsonCount(2, 'recommendations')
        ->assertJsonPath('recommendations.0.status', 'suggested');

    expect($dataset->cleaningRecommendations()->count())->toBe(2);
});

test('AI recommendation preview is required before applying deterministic steps', function () {
    $user = User::factory()->create();
    $records = [
        ['Peak' => '7[2]'],
        ['Peak' => '1[4]'],
    ];
    $dataset = Dataset::factory()->create([
        'uploaded_by_id' => $user->id,
        'headers' => ['Peak'],
        'row_count' => 2,
        'column_count' => 1,
        'original_records' => $records,
        'cleaned_records' => $records,
        'cleaning_log' => [],
        'cleaning_snapshots' => [],
        'profile' => app(DatasetProfiler::class)->profile($records, ['Peak']),
    ]);
    $recommendation = $dataset->cleaningRecommendations()->create([
        'provider' => 'local_heuristic',
        'model' => 'rule-based-fallback',
        'status' => 'suggested',
        'rec_id' => 'rec_peak_brackets',
        'column_name' => 'Peak',
        'issue' => 'Values contain bracketed reference markers.',
        'severity' => 'medium',
        'confidence' => 0.92,
        'risk' => 'low',
        'suggested_steps' => [
            [
                'operation' => 'remove_pattern',
                'column' => 'Peak',
                'parameters' => ['pattern' => '\\[[^\\]]*\\]'],
            ],
            [
                'operation' => 'convert_type',
                'column' => 'Peak',
                'parameters' => ['target_type' => 'numeric'],
            ],
        ],
        'before_examples' => ['7[2]'],
        'after_examples' => ['7'],
        'reason' => 'The bracketed values look like citation markers.',
        'raw_response' => ['recommendations' => []],
    ]);

    $this
        ->actingAs($user)
        ->postJson(route('datasets.cleaning.recommendations.apply', [
            'dataset' => $dataset,
            'recommendation' => $recommendation,
        ]), ['confirmed' => true])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('recommendation');

    $this
        ->actingAs($user)
        ->postJson(route('datasets.cleaning.recommendations.preview', [
            'dataset' => $dataset,
            'recommendation' => $recommendation,
        ]))
        ->assertOk()
        ->assertJsonPath('preview.recommendation_id', $recommendation->id)
        ->assertJsonPath('preview.will_change_dataset', true);

    $response = $this
        ->actingAs($user)
        ->postJson(route('datasets.cleaning.recommendations.apply', [
            'dataset' => $dataset,
            'recommendation' => $recommendation,
        ]), ['confirmed' => true]);

    $response->assertOk()
        ->assertJsonPath('recommendation.status', 'applied');

    $dataset->refresh();

    expect($dataset->cleaned_records)->toBe([
        ['Peak' => 7],
        ['Peak' => 1],
    ])->and($dataset->original_records)->toBe($records)
        ->and($dataset->cleaning_log)->toHaveCount(1)
        ->and($dataset->cleaning_log[0]['source'])->toBe('ai_recommendation')
        ->and($dataset->cleaning_snapshots)->toHaveCount(1);
});
