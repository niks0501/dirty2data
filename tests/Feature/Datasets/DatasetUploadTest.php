<?php

use App\Models\Dataset;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/*
|--------------------------------------------------------------------------
| Dataset Upload & Preview Tests
|--------------------------------------------------------------------------
|
| Covers FEDM requirements #1-6: upload CSV/Excel, table display with
| row/column counts, error handling for invalid files.
|
*/

// ─── Positive: Upload Page Access ───────────────────────────────────────────

test('datasets upload page renders for authenticated user', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->get(route('datasets.index'));

    $response->assertOk();
});

// ─── Negative: Guest Access ─────────────────────────────────────────────────

test('guests are redirected to login from datasets routes', function () {
    $response = $this->get(route('datasets.index'));

    $response->assertRedirect(route('login'));
});

// ─── Positive: CSV Upload ───────────────────────────────────────────────────

test('valid csv upload creates dataset record', function () {
    Storage::fake('local');

    $user = User::factory()->create();

    $csvContent = "Name,Age,City\nJohn,30,NYC\nJane,25,LA\n";
    $file = UploadedFile::fake()->createWithContent('test.csv', $csvContent);

    $response = $this
        ->actingAs($user)
        ->post(route('datasets.store'), [
            'dataset_file' => $file,
        ]);

    $response->assertRedirect();

    $this->assertDatabaseHas('datasets', [
        'uploaded_by_id' => $user->id,
        'original_name' => 'test.csv',
    ]);

    $dataset = Dataset::first();

    expect($dataset->preview['headers'])->toBe(['Name', 'Age', 'City']);
    expect($dataset->preview['row_count'])->toBe(2);
    expect($dataset->preview['column_count'])->toBe(3);
    expect($dataset->preview['sample_rows'])->toHaveCount(2);
});

// ─── Positive: XLSX Upload ──────────────────────────────────────────────────

test('valid xlsx upload creates dataset record', function () {
    Storage::fake('local');

    $user = User::factory()->create();

    $spreadsheet = new Spreadsheet;
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setCellValue('A1', 'Name');
    $sheet->setCellValue('B1', 'Age');
    $sheet->setCellValue('A2', 'Alice');
    $sheet->setCellValue('B2', '28');

    $tempPath = sys_get_temp_dir().'/test_'.uniqid().'.xlsx';
    $writer = new Xlsx($spreadsheet);
    $writer->save($tempPath);

    register_shutdown_function(fn () => @unlink($tempPath));

    $file = new UploadedFile(
        $tempPath,
        'test.xlsx',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        null,
        true,
    );

    $response = $this
        ->actingAs($user)
        ->post(route('datasets.store'), [
            'dataset_file' => $file,
        ]);

    $response->assertRedirect();

    $this->assertDatabaseHas('datasets', [
        'uploaded_by_id' => $user->id,
        'original_name' => 'test.xlsx',
    ]);

    $dataset = Dataset::first();

    expect($dataset->preview['headers'])->toBe(['Name', 'Age']);
    expect($dataset->preview['row_count'])->toBe(1);
    expect($dataset->preview['column_count'])->toBe(2);
});

// ─── Negative: Invalid File Type ────────────────────────────────────────────

test('invalid file type returns validation error', function () {
    Storage::fake('local');

    $user = User::factory()->create();

    $file = UploadedFile::fake()->create('test.pdf', 100, 'application/pdf');

    $response = $this
        ->actingAs($user)
        ->post(route('datasets.store'), [
            'dataset_file' => $file,
        ]);

    $response->assertSessionHasErrors('dataset_file');

    $this->assertDatabaseCount('datasets', 0);
});

// ─── Negative: File Exceeding 10MB ──────────────────────────────────────────

test('file exceeding 10mb returns validation error', function () {
    Storage::fake('local');

    $user = User::factory()->create();

    // 10241 KB > 10240 KB (10 MB)
    $file = UploadedFile::fake()->create('large.csv', 10241);

    $response = $this
        ->actingAs($user)
        ->post(route('datasets.store'), [
            'dataset_file' => $file,
        ]);

    $response->assertSessionHasErrors('dataset_file');

    $this->assertDatabaseCount('datasets', 0);
});

// ─── Negative: Empty CSV ────────────────────────────────────────────────────

test('empty csv file returns validation error', function () {
    Storage::fake('local');

    $user = User::factory()->create();

    $file = UploadedFile::fake()->createWithContent('empty.csv', '');

    $response = $this
        ->actingAs($user)
        ->post(route('datasets.store'), [
            'dataset_file' => $file,
        ]);

    $response->assertSessionHasErrors('dataset_file');

    $this->assertDatabaseCount('datasets', 0);
});

// ─── Negative: No File Provided ─────────────────────────────────────────────

test('missing file returns validation error', function () {
    Storage::fake('local');

    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->post(route('datasets.store'), [
            // 'dataset_file' intentionally omitted
        ]);

    $response->assertSessionHasErrors('dataset_file');

    $this->assertDatabaseCount('datasets', 0);
});

// ─── Positive: Dataset Preview Page ─────────────────────────────────────────

test('dataset preview page displays correct metadata', function () {
    $user = User::factory()->create();

    $dataset = Dataset::create([
        'uploaded_by_id' => $user->id,
        'original_name' => 'sample.csv',
        'disk_path' => 'datasets/1/sample.csv',
        'mime_type' => 'text/csv',
        'size_bytes' => 1024,
        'preview' => [
            'headers' => ['Name', 'Age'],
            'sample_rows' => [['John', '30'], ['Jane', '25']],
            'row_count' => 2,
            'column_count' => 2,
        ],
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('datasets.show', ['dataset' => $dataset]));

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('datasets/show')
            ->where('dataset.originalName', 'sample.csv')
            ->where('dataset.rowCount', 2)
            ->where('dataset.columnCount', 2)
            ->where('dataset.mimeType', 'text/csv')
            ->where('dataset.headers', ['Name', 'Age']),
        );
});

// ─── Negative: Cross-User Isolation ─────────────────────────────────────────

test('dataset preview returns 404 for wrong user', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();

    $dataset = Dataset::create([
        'uploaded_by_id' => $userA->id,
        'original_name' => 'user_a_file.csv',
        'disk_path' => 'datasets/1/file.csv',
        'mime_type' => 'text/csv',
        'size_bytes' => 100,
        'preview' => [
            'headers' => ['X'],
            'sample_rows' => [['1']],
            'row_count' => 1,
            'column_count' => 1,
        ],
    ]);

    $response = $this
        ->actingAs($userB)
        ->get(route('datasets.show', ['dataset' => $dataset]));

    $response->assertStatus(404);
});
