<?php

use App\Services\Datasets\DatasetProfiler;
use App\Services\Datasets\DatasetTypeDetector;

test('dataset profiler reports types missing values duplicates and numeric stats', function () {
    $profile = (new DatasetProfiler)->profile([
        ['Name' => 'Ada', 'Age' => '34', 'City' => 'London'],
        ['Name' => 'Grace', 'Age' => '', 'City' => 'London'],
        ['Name' => 'Ada', 'Age' => '34', 'City' => 'London'],
    ], ['Name', 'Age', 'City']);

    expect($profile['row_count'])->toBe(3)
        ->and($profile['column_count'])->toBe(3)
        ->and($profile['duplicate_count'])->toBe(1);

    $age = collect($profile['columns'])->firstWhere('name', 'Age');

    expect($age['type'])->toBe('numeric')
        ->and($age['missing_count'])->toBe(1)
        ->and($age['unique_count'])->toBe(1)
        ->and($age['distinct_values'])->toBe([
            ['value' => '34', 'count' => 2],
        ])
        ->and($age['sample_values'])->toBe(['34'])
        ->and($age['minimum'])->toBe(34.0)
        ->and($age['maximum'])->toBe(34.0)
        ->and($age['average'])->toBe(34.0)
        ->and($age['median'])->toBe(34.0);
});

test('dataset profiler reports date boolean text and mode metadata', function () {
    $profile = (new DatasetProfiler)->profile([
        ['Joined' => '2026-01-02', 'Active' => 'yes', 'Department' => 'Sales'],
        ['Joined' => '2026-01-01', 'Active' => 'no', 'Department' => 'Support'],
        ['Joined' => '', 'Active' => 'yes', 'Department' => 'Sales'],
    ], ['Joined', 'Active', 'Department']);

    $joined = collect($profile['columns'])->firstWhere('name', 'Joined');
    $active = collect($profile['columns'])->firstWhere('name', 'Active');
    $department = collect($profile['columns'])->firstWhere('name', 'Department');

    expect($joined['type'])->toBe('date')
        ->and($joined['minimum'])->toBe('2026-01-01')
        ->and($joined['maximum'])->toBe('2026-01-02')
        ->and($active['type'])->toBe('boolean')
        ->and($department['type'])->toBe('text')
        ->and($department['mode'])->toBe('Sales')
        ->and($department['distinct_values'][0])->toBe(['value' => 'Sales', 'count' => 2])
        ->and($department['sample_values'])->toBe(['Sales', 'Support']);
});

test('dataset type detector can be reused by other dataset services', function () {
    $detector = new DatasetTypeDetector;

    expect($detector->detect(['10', '20.5']))->toBe('numeric')
        ->and($detector->detect(['2026-01-01', '2026-01-02']))->toBe('date')
        ->and($detector->detect(['true', 'false', 'yes']))->toBe('boolean')
        ->and($detector->detect(['Ada', 'Grace']))->toBe('text')
        ->and($detector->detect(['', null]))->toBe('empty');
});
