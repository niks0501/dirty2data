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

test('dataset cleaner removes bracket references and converts numeric values in a pipeline', function () {
    $dataset = new Dataset([
        'headers' => ['Peak'],
        'cleaned_records' => [
            ['Peak' => '7[2]'],
            ['Peak' => '1[4]'],
        ],
    ]);

    $result = (new DatasetCleaner)->cleanPipeline($dataset, [
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
    ]);

    expect($result['records'])->toBe([
        ['Peak' => 7.0],
        ['Peak' => 1.0],
    ])->and($result['summary']['steps'])->toBe(2)
        ->and($result['summary']['affected_cells'])->toBe(4);
});

test('dataset cleaner extracts numbers from currency strings', function () {
    $dataset = new Dataset([
        'headers' => ['Revenue'],
        'cleaned_records' => [
            ['Revenue' => '$780,000,000'],
            ['Revenue' => 'about 42'],
        ],
    ]);

    $result = (new DatasetCleaner)->clean($dataset, [
        'operation' => 'extract_number',
        'column' => 'Revenue',
    ]);

    expect($result['records'])->toBe([
        ['Revenue' => '780000000'],
        ['Revenue' => '42'],
    ])->and($result['summary']['extracted_cells'])->toBe(2);
});

test('dataset cleaner supports schema-changing split rename and remove operations', function () {
    $dataset = new Dataset([
        'headers' => ['Location', 'Notes'],
        'cleaned_records' => [
            ['Location' => 'Manila, Philippines', 'Notes' => 'keep'],
        ],
    ]);

    $split = (new DatasetCleaner)->clean($dataset, [
        'operation' => 'split_column',
        'column' => 'Location',
        'delimiter' => ',',
        'new_columns' => 'City, Country',
    ]);

    $renamed = (new DatasetCleaner)->clean($dataset, [
        'operation' => 'rename_column',
        'column' => 'Notes',
        'new_column' => 'Comment',
    ], $split['records'], $split['headers']);

    $removed = (new DatasetCleaner)->clean($dataset, [
        'operation' => 'remove_column',
        'column' => 'Location',
    ], $renamed['records'], $renamed['headers']);

    expect($removed['headers'])->toBe(['Comment', 'City', 'Country'])
        ->and($removed['records'][0])->toBe([
            'City' => 'Manila',
            'Country' => 'Philippines',
            'Comment' => 'keep',
        ]);
});

test('column-specific duplicate removal deduplicates by selected columns', function () {
    $records = [
        ['Name' => 'Ada', 'Age' => '34', 'City' => 'London'],
        ['Name' => 'Ada', 'Age' => '35', 'City' => 'London'],
        ['Name' => 'Grace', 'Age' => '40', 'City' => 'Paris'],
    ];
    $dataset = new Dataset([
        'headers' => ['Name', 'Age', 'City'],
        'original_records' => $records,
        'cleaned_records' => $records,
    ]);

    $result = (new DatasetCleaner)->clean($dataset, [
        'operation' => 'remove_duplicates',
        'columns' => ['Name', 'City'],
    ]);

    expect($result['records'])->toHaveCount(2);
    expect($result['summary']['duplicate_scope'])->toBe('selected_columns');
    expect($result['summary']['removed_rows'])->toBe(1);
});

test('column-specific duplicate removal keeps all rows when no column match', function () {
    $records = [
        ['Name' => 'Ada', 'Age' => '30'],
        ['Name' => 'Grace', 'Age' => '40'],
    ];
    $dataset = new Dataset([
        'headers' => ['Name', 'Age'],
        'original_records' => $records,
        'cleaned_records' => $records,
    ]);

    $result = (new DatasetCleaner)->clean($dataset, [
        'operation' => 'remove_duplicates',
        'columns' => ['Name'],
    ]);

    expect($result['records'])->toHaveCount(2);
    expect($result['summary']['removed_rows'])->toBe(0);
});

test('blank detection handles case-insensitive and variant placeholders', function () {
    $dataset = new Dataset([
        'headers' => ['Status'],
        'cleaned_records' => [
            ['Status' => 'N/A'],
            ['Status' => 'N.A.'],
            ['Status' => 'not_available'],
            ['Status' => '---'],
            ['Status' => 'null'],
            ['Status' => 'N u l l'],
        ],
    ]);

    $result = (new DatasetCleaner)->clean($dataset, [
        'operation' => 'fill_missing',
        'column' => 'Status',
        'method' => 'custom',
        'value' => 'FILLED',
    ]);

    expect($result['summary']['filled_cells'])->toBe(6);
    foreach ($result['records'] as $record) {
        expect($record['Status'])->toBe('FILLED');
    }
});

test('boolean conversion accepts extended true and false values', function () {
    $dataset = new Dataset([
        'headers' => ['Flag'],
        'cleaned_records' => [
            ['Flag' => 'y'],
            ['Flag' => 't'],
            ['Flag' => 'on'],
            ['Flag' => 'n'],
            ['Flag' => 'f'],
            ['Flag' => 'off'],
        ],
    ]);

    $result = (new DatasetCleaner)->clean($dataset, [
        'operation' => 'convert_type',
        'column' => 'Flag',
        'target_type' => 'boolean',
    ]);

    expect($result['records'][0]['Flag'])->toBeTrue();
    expect($result['records'][1]['Flag'])->toBeTrue();
    expect($result['records'][2]['Flag'])->toBeTrue();
    expect($result['records'][3]['Flag'])->toBeFalse();
    expect($result['records'][4]['Flag'])->toBeFalse();
    expect($result['records'][5]['Flag'])->toBeFalse();
    expect($result['summary']['converted_cells'])->toBe(6);
});
