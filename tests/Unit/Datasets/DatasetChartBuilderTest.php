<?php

use App\Models\Dataset;
use App\Services\Datasets\DatasetChartBuilder;

test('dataset chart builder creates category chart payloads', function () {
    $dataset = new Dataset([
        'headers' => ['City', 'Sales'],
        'cleaned_records' => [
            ['City' => 'London', 'Sales' => '10'],
            ['City' => 'London', 'Sales' => '12'],
            ['City' => 'Manila', 'Sales' => '8'],
        ],
    ]);

    $chart = (new DatasetChartBuilder)->build($dataset, 'bar', 'City', null);

    expect($chart['message'])->toBeNull()
        ->and($chart['data'][0])->toBe(['name' => 'London', 'value' => 2]);
});

test('dataset chart builder creates line chart payloads', function () {
    $dataset = new Dataset([
        'headers' => ['Date', 'Sales'],
        'cleaned_records' => [
            ['Date' => '2026-01-02', 'Sales' => '20'],
            ['Date' => '2026-01-01', 'Sales' => '10'],
        ],
    ]);

    $chart = (new DatasetChartBuilder)->build($dataset, 'line', 'Date', 'Sales');

    expect($chart['message'])->toBeNull()
        ->and($chart['data'][0])->toBe(['name' => '2026-01-01', 'value' => 10.0]);
});

test('dataset chart builder creates histogram payloads', function () {
    $dataset = new Dataset([
        'headers' => ['Age'],
        'cleaned_records' => [
            ['Age' => '10'],
            ['Age' => '20'],
            ['Age' => '30'],
            ['Age' => '40'],
        ],
    ]);

    $chart = (new DatasetChartBuilder)->build($dataset, 'histogram', 'Age', null);

    expect($chart['message'])->toBeNull()
        ->and($chart['type'])->toBe('histogram')
        ->and($chart['data'])->not->toBeEmpty()
        ->and($chart['data'][0])->toHaveKeys(['name', 'value', 'bin_min', 'bin_max']);
});

test('dataset chart builder creates scatter payloads', function () {
    $dataset = new Dataset([
        'headers' => ['Age', 'Score'],
        'cleaned_records' => [
            ['Age' => '10', 'Score' => '80'],
            ['Age' => '20', 'Score' => '90'],
        ],
    ]);

    $chart = (new DatasetChartBuilder)->build($dataset, 'scatter', 'Age', 'Score');

    expect($chart['message'])->toBeNull()
        ->and($chart['type'])->toBe('scatter')
        ->and($chart['data'][0]['x'])->toBe(10.0)
        ->and($chart['data'][0]['y'])->toBe(80.0);
});
