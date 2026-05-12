<?php

use App\Models\Dataset;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Excel as ExcelReader;
use Maatwebsite\Excel\Facades\Excel;

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
    $this->withoutVite();

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
    expect($dataset->original_records)->toHaveCount(2);
    expect($dataset->cleaned_records)->toBe($dataset->original_records);
    expect($dataset->profile['row_count'])->toBe(2);
});

// ─── Positive: XLSX Upload ──────────────────────────────────────────────────

test('valid xlsx upload creates dataset record', function () {
    Storage::fake('local');

    $user = User::factory()->create();

    Excel::store(new class implements FromArray
    {
        public function array(): array
        {
            return [
                ['Name', 'Age'],
                ['Alice', '28'],
            ];
        }
    }, 'test.xlsx', 'local', ExcelReader::XLSX);

    $tempPath = Storage::disk('local')->path('test.xlsx');

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

// ─── Negative: File Exceeding 50MB ──────────────────────────────────────────

test('file exceeding 50mb returns validation error', function () {
    Storage::fake('local');

    $user = User::factory()->create();

    $file = UploadedFile::fake()->create('large.csv', 51201);

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
    $this->withoutVite();

    $user = User::factory()->create();

    $dataset = Dataset::factory()->create([
        'uploaded_by_id' => $user->id,
        'original_name' => 'sample.csv',
        'headers' => ['Name', 'Age'],
        'row_count' => 2,
        'column_count' => 2,
        'original_records' => [['Name' => 'John', 'Age' => '30'], ['Name' => 'Jane', 'Age' => '25']],
        'cleaned_records' => [['Name' => 'John', 'Age' => '30'], ['Name' => 'Jane', 'Age' => '25']],
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

    $dataset = Dataset::factory()->create([
        'uploaded_by_id' => $userA->id,
        'original_name' => 'user_a_file.csv',
    ]);

    $response = $this
        ->actingAs($userB)
        ->get(route('datasets.show', ['dataset' => $dataset]));

    $response->assertStatus(404);
});
