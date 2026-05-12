<?php

namespace App\Http\Controllers\Datasets;

use App\Http\Controllers\Controller;
use App\Http\Requests\Datasets\ChartDatasetRequest;
use App\Http\Requests\Datasets\CleanDatasetRequest;
use App\Http\Requests\Datasets\StoreDatasetRequest;
use App\Models\Dataset;
use App\Services\Datasets\DatasetChartBuilder;
use App\Services\Datasets\DatasetChartRecommender;
use App\Services\Datasets\DatasetCleaner;
use App\Services\Datasets\DatasetCleaningPreviewer;
use App\Services\Datasets\DatasetPreviewParser;
use App\Services\Datasets\DatasetProfiler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class DatasetController extends Controller
{
    /**
     * Display the dataset upload page.
     */
    public function index(Request $request): Response
    {
        $datasets = Dataset::query()
            ->where('uploaded_by_id', $request->user()->id)
            ->latest()
            ->get()
            ->map(fn (Dataset $dataset): array => [
                'id' => $dataset->id,
                'originalName' => $dataset->original_name,
                'rowCount' => $dataset->row_count,
                'columnCount' => $dataset->column_count,
                'createdAt' => $dataset->created_at->toISOString(),
            ]);

        return Inertia::render('datasets/index', [
            'datasets' => $datasets,
        ]);
    }

    /**
     * Store a newly uploaded dataset file.
     */
    public function store(
        StoreDatasetRequest $request,
        DatasetPreviewParser $parser,
        DatasetProfiler $profiler,
    ): RedirectResponse {
        $file = $request->file('dataset_file');
        $extension = $file->getClientOriginalExtension();
        $user = $request->user();
        $uuidFilename = Str::uuid().'.'.$extension;

        $storedPath = Storage::disk('local')->putFileAs(
            'datasets/'.$user->id,
            $file,
            $uuidFilename,
        );

        $parsed = $parser->parse($file->getRealPath(), $extension);
        $profile = $profiler->profile($parsed['records'], $parsed['headers']);

        $dataset = Dataset::create([
            'uploaded_by_id' => $user->id,
            'original_name' => $file->getClientOriginalName(),
            'disk_path' => $storedPath,
            'mime_type' => $file->getMimeType(),
            'extension' => strtolower($extension),
            'size_bytes' => $file->getSize(),
            'row_count' => $parsed['row_count'],
            'column_count' => $parsed['column_count'],
            'headers' => $parsed['headers'],
            'original_records' => $parsed['records'],
            'cleaned_records' => $parsed['records'],
            'preview' => [
                'headers' => $parsed['headers'],
                'sample_rows' => $parsed['sample_rows'],
                'row_count' => $parsed['row_count'],
                'column_count' => $parsed['column_count'],
            ],
            'profile' => $profile,
            'cleaning_log' => [],
        ]);

        return to_route('datasets.show', [
            'dataset' => $dataset->id,
        ])->with('toast', [
            'type' => 'success',
            'message' => __('Dataset uploaded successfully.'),
        ]);
    }

    /**
     * Display the dataset workflow page.
     */
    public function show(
        Request $request,
        Dataset $dataset,
        DatasetChartBuilder $chartBuilder,
        DatasetChartRecommender $chartRecommender,
    ): Response {
        $this->authorizeDataset($request, $dataset);

        $records = $dataset->cleaned_records ?? [];
        $headers = $dataset->headers ?? [];
        $profile = $dataset->profile;
        $chartRecommendations = $chartRecommender->recommend($dataset);
        $defaultRecommendation = $chartRecommendations[0] ?? null;
        $page = max((int) $request->integer('page', 1), 1);
        $perPage = 15;
        $previewRows = array_slice($records, ($page - 1) * $perPage, $perPage);

        $chartType = $request->string('chart_type')->toString() ?: ($defaultRecommendation['type'] ?? 'bar');
        $xColumn = $request->string('x_column')->toString() ?: ($defaultRecommendation['x_column'] ?? ($headers[0] ?? null));
        $yColumn = $request->string('y_column')->toString() ?: ($defaultRecommendation['y_column'] ?? null);
        $selectedColumn = $request->string('column')->toString() ?: ($headers[0] ?? null);
        $selectedColumnProfile = $this->selectedColumnProfile($profile, $selectedColumn);

        return Inertia::render('datasets/show', [
            'dataset' => [
                'id' => $dataset->id,
                'originalName' => $dataset->original_name,
                'mimeType' => $dataset->mime_type,
                'extension' => $dataset->extension,
                'sizeBytes' => $dataset->size_bytes,
                'rowCount' => count($records),
                'originalRowCount' => count($dataset->original_records ?? []),
                'columnCount' => $dataset->column_count,
                'headers' => $headers,
                'previewRows' => $previewRows,
                'profile' => $profile,
                'selectedColumn' => $selectedColumn,
                'selectedColumnProfile' => $selectedColumnProfile,
                'cleaningLog' => $dataset->cleaning_log ?? [],
                'pagination' => [
                    'page' => $page,
                    'perPage' => $perPage,
                    'total' => count($records),
                    'lastPage' => max((int) ceil(count($records) / $perPage), 1),
                ],
                'chartRecommendations' => $chartRecommendations,
                'chart' => $chartBuilder->build($dataset, $chartType, $xColumn, $yColumn),
                'createdAt' => $dataset->created_at->toISOString(),
            ],
        ]);
    }

    /**
     * Apply a manual cleaning action to the working cleaned dataset.
     */
    public function clean(
        CleanDatasetRequest $request,
        Dataset $dataset,
        DatasetCleaner $cleaner,
        DatasetProfiler $profiler,
    ): RedirectResponse {
        $this->authorizeDataset($request, $dataset);

        try {
            $result = $cleaner->clean($dataset, $request->validated());
        } catch (\InvalidArgumentException $e) {
            throw ValidationException::withMessages([
                'operation' => $e->getMessage(),
            ]);
        }

        $profile = $profiler->profile($result['records'], $dataset->headers ?? []);
        $log = $dataset->cleaning_log ?? [];
        $log[] = $result['log'];

        $dataset->update([
            'cleaned_records' => $result['records'],
            'row_count' => count($result['records']),
            'profile' => $profile,
            'cleaning_log' => $log,
        ]);

        return to_route('datasets.show', ['dataset' => $dataset])->with('toast', [
            'type' => 'success',
            'message' => __('Cleaning action applied.'),
        ]);
    }

    /**
     * Preview a manual cleaning action without mutating the working dataset.
     */
    public function previewClean(
        CleanDatasetRequest $request,
        Dataset $dataset,
        DatasetCleaningPreviewer $previewer,
    ): JsonResponse {
        $this->authorizeDataset($request, $dataset);

        try {
            $preview = $previewer->preview($dataset, $request->validated());
        } catch (\InvalidArgumentException $e) {
            throw ValidationException::withMessages([
                'operation' => $e->getMessage(),
            ]);
        }

        return response()->json([
            'preview' => $preview,
        ]);
    }

    /**
     * Return a compact chart payload for async consumers.
     */
    public function chart(ChartDatasetRequest $request, Dataset $dataset, DatasetChartBuilder $chartBuilder): JsonResponse
    {
        $this->authorizeDataset($request, $dataset);

        $validated = $request->validated();

        return response()->json([
            'chart' => $chartBuilder->build(
                $dataset,
                $validated['chart_type'],
                $validated['x_column'],
                $validated['y_column'] ?? null,
            ),
        ]);
    }

    private function authorizeDataset(Request $request, Dataset $dataset): void
    {
        abort_if($dataset->uploaded_by_id !== $request->user()->id, 404);
    }

    /**
     * @param  array<string, mixed>|null  $profile
     * @return array<string, mixed>|null
     */
    private function selectedColumnProfile(?array $profile, ?string $selectedColumn): ?array
    {
        if (! $profile || ! $selectedColumn) {
            return null;
        }

        foreach (($profile['columns'] ?? []) as $columnProfile) {
            if (($columnProfile['name'] ?? null) === $selectedColumn) {
                return $columnProfile;
            }
        }

        return null;
    }
}
