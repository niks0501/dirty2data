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
