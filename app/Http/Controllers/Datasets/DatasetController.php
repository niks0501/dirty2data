<?php

namespace App\Http\Controllers\Datasets;

use App\Http\Controllers\Controller;
use App\Http\Requests\Datasets\ChartDatasetRequest;
use App\Http\Requests\Datasets\CleanDatasetRequest;
use App\Http\Requests\Datasets\StoreDatasetRequest;
use App\Jobs\ProcessAfterQualityScore;
use App\Jobs\ProcessDatasetUpload;
use App\Models\Dataset;
use App\Models\DatasetCleaningRecommendation;
use App\Models\DatasetQualityScore;
use App\Services\Datasets\DatasetChartBuilder;
use App\Services\Datasets\DatasetChartRecommender;
use App\Services\Datasets\DatasetCleaner;
use App\Services\Datasets\DatasetCleaningPreviewer;
use App\Services\Datasets\DatasetComparisonBuilder;
use App\Services\Datasets\DatasetInsightsBuilder;
use App\Services\Datasets\DatasetPreviewParser;
use App\Services\Datasets\DatasetProfiler;
use App\Services\Datasets\DatasetQualityScoreClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class DatasetController extends Controller
{
    private const int SYNC_ROW_LIMIT = 200_000;

    private const int MAX_STORED_ROWS = 5_000;

    public function index(Request $request): Response
    {
        $datasets = Dataset::query()
            ->where('uploaded_by_id', $request->user()->id)
            ->select(['id', 'original_name', 'row_count', 'column_count', 'created_at', 'status'])
            ->latest()
            ->get()
            ->map(fn (Dataset $dataset): array => [
                'id' => $dataset->id,
                'originalName' => $dataset->original_name,
                'rowCount' => $dataset->row_count,
                'columnCount' => $dataset->column_count,
                'createdAt' => $dataset->created_at->toISOString(),
                'status' => $dataset->status,
            ]);

        return Inertia::render('datasets/index', [
            'datasets' => $datasets,
        ]);
    }

    public function store(
        StoreDatasetRequest $request,
        DatasetPreviewParser $parser,
        DatasetProfiler $profiler,
        DatasetQualityScoreClient $scoreClient,
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

        $filePath = $file->getRealPath();
        $rowCount = $parser->countRows($filePath, $extension);

        if ($rowCount > self::SYNC_ROW_LIMIT) {
            $dataset = Dataset::create([
                'uploaded_by_id' => $user->id,
                'original_name' => $file->getClientOriginalName(),
                'disk_path' => $storedPath,
                'mime_type' => $file->getMimeType(),
                'extension' => strtolower($extension),
                'size_bytes' => $file->getSize(),
                'row_count' => $rowCount,
                'column_count' => 0,
                'headers' => [],
                'original_records' => [],
                'cleaned_records' => [],
                'preview' => null,
                'profile' => null,
                'cleaning_log' => [],
                'status' => 'processing',
            ]);

            ProcessDatasetUpload::dispatch($dataset);

            return to_route('datasets.show', [
                'dataset' => $dataset->id,
            ])->with('toast', [
                'type' => 'info',
                'message' => __('Large dataset detected. Processing in the background — you will see results shortly.'),
            ]);
        }

        try {
            $parsed = $parser->parse($filePath, $extension);
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            throw ValidationException::withMessages([
                'dataset_file' => $e->getMessage(),
            ]);
        }
        $profile = $profiler->profile($parsed['records'], $parsed['headers']);

        $previewLimit = min($parsed['row_count'], self::MAX_STORED_ROWS);
        $previewRecords = array_slice($parsed['records'], 0, $previewLimit);

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
            'original_records' => $previewRecords,
            'cleaned_records' => $previewRecords,
            'preview' => [
                'headers' => $parsed['headers'],
                'sample_rows' => array_slice($previewRecords, 0, 15),
                'row_count' => $parsed['row_count'],
                'column_count' => $parsed['column_count'],
                'preview_note' => $parsed['row_count'] > self::MAX_STORED_ROWS
                    ? 'Showing first '.number_format(self::MAX_STORED_ROWS).' rows of '.number_format($parsed['row_count']).'.'
                    : null,
            ],
            'profile' => $profile,
            'cleaning_log' => [],
            'status' => 'ready',
        ]);

        $this->requestQualityScore($dataset, $scoreClient);

        return to_route('datasets.show', [
            'dataset' => $dataset->id,
        ])->with('toast', [
            'type' => 'success',
            'message' => __('Dataset uploaded successfully.'),
        ]);
    }

    public function show(
        Request $request,
        Dataset $dataset,
        DatasetChartBuilder $chartBuilder,
        DatasetChartRecommender $chartRecommender,
    ): Response {
        $this->authorizeDataset($request, $dataset);

        $isProcessing = $dataset->status === 'processing';

        $records = $dataset->cleaned_records ?? [];
        $headers = $dataset->headers ?? [];
        $profile = $dataset->profile;

        $chartRecommendations = $isProcessing ? [] : $chartRecommender->recommend($dataset);
        $defaultRecommendation = $chartRecommendations[0] ?? null;

        $page = max((int) $request->integer('page', 1), 1);
        $perPage = 15;
        $previewRows = $isProcessing ? [] : array_slice($records, ($page - 1) * $perPage, $perPage);

        $chartType = $request->string('chart_type')->toString() ?: ($defaultRecommendation['type'] ?? 'bar');
        $xColumn = $request->string('x_column')->toString() ?: ($defaultRecommendation['x_column'] ?? ($headers[0] ?? null));
        $yColumn = $request->string('y_column')->toString() ?: ($defaultRecommendation['y_column'] ?? '');
        $selectedColumn = $request->string('column')->toString() ?: ($headers[0] ?? null);
        $selectedColumnProfile = $this->selectedColumnProfile($profile, $selectedColumn);
        $chartOptions = [
            'aggregation' => $request->string('aggregation')->toString() ?: 'sum',
            'bin_count' => $request->integer('bin_count', 8),
            'date_group' => $request->string('date_group')->toString() ?: 'day',
        ];

        $chart = $isProcessing
            ? ['type' => 'bar', 'title' => 'Processing…', 'data' => [], 'message' => 'Dataset is still being processed.', 'x_column' => null, 'y_column' => null, 'reason' => null, 'metadata' => null]
            : $chartBuilder->build($dataset, $chartType, $xColumn, $yColumn !== '' ? $yColumn : null, $chartOptions);

        $previewNote = $dataset->preview['preview_note'] ?? null;

        $beforeScore = $dataset->latestBeforeQualityScore();
        $afterScore = $dataset->latestAfterQualityScore();

        return Inertia::render('datasets/show', [
            'beforeScore' => $this->formatQualityScore($beforeScore),
            'afterScore' => $this->formatQualityScore($afterScore),
            'dataset' => [
                'id' => $dataset->id,
                'originalName' => $dataset->original_name,
                'mimeType' => $dataset->mime_type,
                'extension' => $dataset->extension,
                'sizeBytes' => $dataset->size_bytes,
                'rowCount' => $dataset->row_count,
                'originalRowCount' => $dataset->row_count,
                'columnCount' => $dataset->column_count,
                'headers' => $headers,
                'previewRows' => $previewRows,
                'previewNote' => $previewNote,
                'profile' => $profile,
                'selectedColumn' => $isProcessing ? null : $selectedColumn,
                'selectedColumnProfile' => $isProcessing ? null : $selectedColumnProfile,
                'cleaningLog' => $dataset->cleaning_log ?? [],
                'cleaningSnapshots' => $dataset->cleaning_snapshots ?? [],
                'cleaningRecommendations' => $dataset->cleaningRecommendations()
                    ->latest()
                    ->limit(20)
                    ->get()
                    ->map(fn (DatasetCleaningRecommendation $recommendation): array => $this->formatCleaningRecommendation($recommendation))
                    ->values(),
                'pagination' => [
                    'page' => $page,
                    'perPage' => $perPage,
                    'total' => $isProcessing ? 0 : count($records),
                    'lastPage' => $isProcessing ? 1 : max((int) ceil(count($records) / $perPage), 1),
                ],
                'chartRecommendations' => $chartRecommendations,
                'chart' => $chart,
                'createdAt' => $dataset->created_at->toISOString(),
                'status' => $dataset->status,
                'processing' => [
                    'progress' => $dataset->processing_progress,
                    'rowsProcessed' => $dataset->processing_rows_processed,
                    'startedAt' => $dataset->processing_started_at?->toISOString(),
                    'finishedAt' => $dataset->processing_finished_at?->toISOString(),
                    'error' => $dataset->processing_error,
                ],
            ],
        ]);
    }

    public function clean(
        CleanDatasetRequest $request,
        Dataset $dataset,
        DatasetCleaner $cleaner,
        DatasetProfiler $profiler,
    ): RedirectResponse {
        $this->authorizeDataset($request, $dataset);

        $snapshots = $cleaner->pushSnapshot($dataset);

        try {
            $result = $cleaner->clean($dataset, $request->validated());
        } catch (\InvalidArgumentException $e) {
            throw ValidationException::withMessages([
                'operation' => $e->getMessage(),
            ]);
        }

        $headers = $result['headers'] ?? ($dataset->headers ?? []);
        $profile = $profiler->profile($result['records'], $headers);
        $log = $dataset->cleaning_log ?? [];
        $log[] = $result['log'];

        $dataset->update([
            'headers' => $headers,
            'cleaned_records' => $result['records'],
            'row_count' => count($result['records']),
            'column_count' => count($headers),
            'profile' => $profile,
            'cleaning_log' => $log,
            'cleaning_snapshots' => $snapshots,
        ]);

        ProcessAfterQualityScore::dispatch($dataset, $profile)->afterResponse();

        return to_route('datasets.show', ['dataset' => $dataset])->with('toast', [
            'type' => 'success',
            'message' => __('Cleaning action applied.'),
        ]);
    }

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
                $this->chartOptions($request),
            ),
        ]);
    }

    public function comparison(Request $request, Dataset $dataset, DatasetComparisonBuilder $comparisonBuilder): JsonResponse
    {
        $this->authorizeDataset($request, $dataset);

        $page = max((int) $request->integer('page', 1), 1);
        $perPage = min(max((int) $request->integer('per_page', 15), 1), 100);

        return response()->json(
            $comparisonBuilder->build($dataset, $page, $perPage)
        );
    }

    public function insights(Request $request, Dataset $dataset, DatasetInsightsBuilder $insightsBuilder): JsonResponse
    {
        $this->authorizeDataset($request, $dataset);

        return response()->json(
            $insightsBuilder->build($dataset)
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function chartOptions(Request $request): array
    {
        return [
            'aggregation' => $request->string('aggregation')->toString() ?: 'sum',
            'bin_count' => $request->integer('bin_count', 8),
            'date_group' => $request->string('date_group')->toString() ?: 'day',
        ];
    }

    private function authorizeDataset(Request $request, Dataset $dataset): void
    {
        abort_if($dataset->uploaded_by_id !== $request->user()->id, 404);
    }

    /**
     * Call the Python data processing service to calculate and persist the quality score.
     *
     * Errors are logged but do not block the upload flow — the dataset stays usable
     * even if the Python service is temporarily unavailable.
     */
    private function requestQualityScore(Dataset $dataset, DatasetQualityScoreClient $scoreClient): void
    {
        try {
            $filePath = Storage::disk('local')->path($dataset->disk_path);

            $result = $scoreClient->score($filePath, $dataset->original_name);
            $pm = $result['profile_metrics'] ?? [];
            $bd = $result['breakdown'] ?? [];

            DatasetQualityScore::create([
                'dataset_id' => $dataset->id,
                'score_type' => 'before',
                'quality_score' => (int) round((float) ($result['final_score'] ?? 0)),
                'status' => (string) ($result['status'] ?? 'Unknown'),
                'completeness_score' => isset($bd['completeness']['score'])
                    ? (float) $bd['completeness']['score'] : null,
                'uniqueness_score' => isset($bd['uniqueness']['score'])
                    ? (float) $bd['uniqueness']['score'] : null,
                'validity_score' => isset($bd['validity']['score'])
                    ? (float) $bd['validity']['score'] : null,
                'consistency_score' => isset($bd['consistency']['score'])
                    ? (float) $bd['consistency']['score'] : null,
                'type_accuracy_score' => isset($bd['type_accuracy']['score'])
                    ? (float) $bd['type_accuracy']['score'] : null,
                'missing_values' => (int) ($pm['missing_cell_count'] ?? 0),
                'duplicate_rows' => (int) ($pm['duplicate_count'] ?? 0),
                'invalid_values' => (int) ($pm['invalid_cell_count'] ?? 0),
                'inconsistent_columns' => 0,
                'type_issue_columns' => 0,
                'breakdown' => $bd ?: null,
                'issues_summary' => $result['issues_summary'] ?? null,
                'recommendation_summary' => $result['recommendation_summary'] ?? null,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Quality score calculation skipped.', [
                'dataset_id' => $dataset->id,
                'reason' => $e->getMessage(),
            ]);
        }
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

    /**
     * Format a quality score model into the shape expected by the React frontend.
     *
     * @return array<string, mixed>|null
     */
    private function formatQualityScore(?DatasetQualityScore $score): ?array
    {
        if (! $score) {
            return null;
        }

        return [
            'score_type' => $score->score_type,
            'quality_score' => $score->quality_score,
            'status' => $score->status,
            'breakdown' => [
                'completeness' => (float) $score->completeness_score,
                'uniqueness' => (float) $score->uniqueness_score,
                'validity' => (float) $score->validity_score,
                'consistency' => (float) $score->consistency_score,
                'type_accuracy' => (float) $score->type_accuracy_score,
            ],
            'issues_summary' => [
                'missing_values' => (int) $score->missing_values,
                'duplicate_rows' => (int) $score->duplicate_rows,
                'invalid_values' => (int) $score->invalid_values,
                'inconsistent_columns' => (int) $score->inconsistent_columns,
                'type_issue_columns' => (int) $score->type_issue_columns,
            ],
            'issues' => $score->issues_summary,
            'recommendations' => $score->recommendation_summary,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formatCleaningRecommendation(DatasetCleaningRecommendation $recommendation): array
    {
        return [
            'id' => $recommendation->id,
            'rec_id' => $recommendation->rec_id,
            'provider' => $recommendation->provider,
            'model' => $recommendation->model,
            'status' => $recommendation->status,
            'column_name' => $recommendation->column_name,
            'issue' => $recommendation->issue,
            'severity' => $recommendation->severity,
            'confidence' => $recommendation->confidence,
            'risk' => $recommendation->risk,
            'suggested_steps' => $recommendation->suggested_steps ?? [],
            'before_examples' => $recommendation->before_examples ?? [],
            'after_examples' => $recommendation->after_examples ?? [],
            'reason' => $recommendation->reason,
            'created_at' => $recommendation->created_at?->toISOString(),
        ];
    }
}
