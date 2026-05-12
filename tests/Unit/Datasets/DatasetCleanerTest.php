<?php

use App\Models\Dataset;
use App\Services\Datasets\DatasetCleaner;

test('dataset cleaner removes duplicates without changing original records', function () {
    $original = [
        ['Name' => 'Ada', 'Age' => '34'],
        ['Name' => 'Ada', 'Age' => '34'],
        ['Name' => 'Grace', 'Age' => null],
    ];
    $dataset = new Dataset([
        'headers' => ['Name', 'Age'],
        'original_records' => $original,
        'cleaned_records' => $original,
    ]);

    $result = (new DatasetCleaner)->clean($dataset, [
        'operation' => 'remove_duplicates',
    ]);

    expect($result['records'])->toHaveCount(2)
        ->and($dataset->original_records)->toBe($original)
        ->and($result['summary']['removed_rows'])->toBe(1);
});

test('dataset cleaner fills missing values using median', function () {
    $dataset = new Dataset([
        'headers' => ['Name', 'Age'],
        'cleaned_records' => [
            ['Name' => 'Ada', 'Age' => '30'],
            ['Name' => 'Grace', 'Age' => '40'],
            ['Name' => 'Linus', 'Age' => null],
        ],
    ]);

    $result = (new DatasetCleaner)->clean($dataset, [
        'operation' => 'fill_missing',
        'column' => 'Age',
        'method' => 'median',
    ]);

    expect($result['records'][2]['Age'])->toBe(35.0)
        ->and($result['summary']['filled_cells'])->toBe(1);
});

test('dataset cleaner standardizes text values', function () {
    $dataset = new Dataset([
        'headers' => ['Name'],
        'cleaned_records' => [
            ['Name' => ' ada lovelace '],
            ['Name' => 'GRACE HOPPER'],
        ],
    ]);

    $result = (new DatasetCleaner)->clean($dataset, [
        'operation' => 'standardize_text',
        'column' => 'Name',
        'text_format' => 'title',
    ]);

    expect($result['records'][0]['Name'])->toBe('Ada Lovelace')
        ->and($result['records'][1]['Name'])->toBe('Grace Hopper')
        ->and($result['summary']['standardized_cells'])->toBe(2);
});

test('dataset cleaner filters invalid rows by selected type', function () {
    $dataset = new Dataset([
        'headers' => ['Age'],
        'cleaned_records' => [
            ['Age' => '30'],
            ['Age' => 'unknown'],
            ['Age' => ''],
        ],
    ]);

    $result = (new DatasetCleaner)->clean($dataset, [
        'operation' => 'filter_invalid',
        'column' => 'Age',
        'target_type' => 'numeric',
    ]);

    expect($result['records'])->toBe([
        ['Age' => '30'],
    ])->and($result['summary']['removed_rows'])->toBe(2);
});
