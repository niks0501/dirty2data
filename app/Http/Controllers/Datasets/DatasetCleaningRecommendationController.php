<?php

namespace App\Http\Controllers\Datasets;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessAfterQualityScore;
use App\Models\Dataset;
use App\Models\DatasetCleaningRecommendation;
use App\Services\AI\CleaningRecommendationManager;
use App\Services\Datasets\DatasetAiContextBuilder;
use App\Services\Datasets\DatasetCleaner;
use App\Services\Datasets\DatasetCleaningPreviewer;
use App\Services\Datasets\DatasetProfiler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class DatasetCleaningRecommendationController extends Controller
{
    public function index(Request $request, Dataset $dataset): JsonResponse
    {
        $this->authorizeDataset($request, $dataset);

        return response()->json([
            'recommendations' => $dataset->cleaningRecommendations()
                ->latest()
                ->get()
                ->map(fn (DatasetCleaningRecommendation $recommendation): array => $this->formatRecommendation($recommendation))
                ->values(),
        ]);
    }

    public function generate(
        Request $request,
        Dataset $dataset,
        DatasetAiContextBuilder $contextBuilder,
        CleaningRecommendationManager $manager,
        DatasetCleaningPreviewer $previewer,
    ): JsonResponse {
        $this->authorizeDataset($request, $dataset);

        $context = $contextBuilder->build($dataset);
        $result = $manager->recommend($context, $dataset->headers ?? []);

        $result['recommendations'] = $this->filterNoOpRecommendations($dataset, $result['recommendations'], $previewer);

        $dataset->cleaningRecommendations()
            ->where('status', 'suggested')
            ->update(['status' => 'expired']);

        $stored = [];

        foreach ($result['recommendations'] as $recommendation) {
            $stored[] = $dataset->cleaningRecommendations()->create([
                'provider' => $result['provider'],
                'model' => $result['model'],
                'status' => 'suggested',
                'rec_id' => $recommendation['id'],
                'column_name' => $recommendation['column'],
                'issue' => $recommendation['issue'],
                'severity' => $recommendation['severity'],
                'confidence' => $recommendation['confidence'],
                'risk' => $recommendation['risk'],
                'suggested_steps' => $recommendation['suggested_steps'],
                'before_examples' => $recommendation['before_examples'],
                'after_examples' => $recommendation['after_examples'],
                'reason' => $recommendation['reason'],
                'raw_response' => $result['raw_response'],
            ]);
        }

        return response()->json([
            'provider' => $result['provider'],
            'model' => $result['model'],
            'source' => $result['source'],
            'fallback_reason' => $result['fallback_reason'],
            'recommendations' => array_map(fn (DatasetCleaningRecommendation $recommendation): array => $this->formatRecommendation($recommendation), $stored),
        ]);
    }

    public function preview(
        Request $request,
        Dataset $dataset,
        DatasetCleaningRecommendation $recommendation,
        DatasetCleaningPreviewer $previewer,
    ): JsonResponse {
        $this->authorizeDataset($request, $dataset);
        $this->authorizeRecommendation($dataset, $recommendation);

        try {
            $preview = $previewer->previewPipeline($dataset, $recommendation->suggested_steps ?? []);
        } catch (\InvalidArgumentException $e) {
            throw ValidationException::withMessages([
                'recommendation' => $e->getMessage(),
            ]);
        }

        if ($recommendation->status === 'suggested') {
            $recommendation->update(['status' => 'accepted']);
        }

        return response()->json([
            'preview' => array_merge($preview, [
                'risk' => $recommendation->risk,
                'reason' => $recommendation->reason,
                'recommendation_id' => $recommendation->id,
            ]),
        ]);
    }

    public function apply(
        Request $request,
        Dataset $dataset,
        DatasetCleaningRecommendation $recommendation,
        DatasetCleaner $cleaner,
        DatasetProfiler $profiler,
    ): JsonResponse {
        $this->authorizeDataset($request, $dataset);
        $this->authorizeRecommendation($dataset, $recommendation);

        $validated = $request->validate([
            'confirmed' => ['accepted'],
        ]);

        if (($validated['confirmed'] ?? false) === false) {
            throw ValidationException::withMessages([
                'confirmed' => 'Preview and confirm this recommendation before applying it.',
            ]);
        }

        if ($recommendation->status !== 'accepted') {
            throw ValidationException::withMessages([
                'recommendation' => 'Preview this recommendation before applying it.',
            ]);
        }

        $snapshots = $cleaner->pushSnapshot($dataset);

        try {
            $result = $cleaner->cleanPipeline($dataset, $recommendation->suggested_steps ?? [], null, null, [
                'source' => 'ai_recommendation',
                'recommendation_id' => $recommendation->id,
                'explanation' => $recommendation->reason,
            ]);
        } catch (\InvalidArgumentException $e) {
            throw ValidationException::withMessages([
                'recommendation' => $e->getMessage(),
            ]);
        }

        $headers = $result['headers'];
        $profile = $profiler->profile($result['records'], $headers);
        $log = $dataset->cleaning_log ?? [];
        $log[] = [
            'operation' => 'ai_recommendation_pipeline',
            'column' => $recommendation->column_name,
            'source' => 'ai_recommendation',
            'recommendation_id' => $recommendation->id,
            'explanation' => $recommendation->reason,
            'summary' => $result['summary'],
            'steps' => $result['logs'],
            'applied_at' => now()->toISOString(),
        ];

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

        $recommendation->update(['status' => 'applied']);

        return response()->json([
            'message' => 'AI recommendation applied to the cleaned working copy.',
            'recommendation' => $this->formatRecommendation($recommendation->fresh()),
        ]);
    }

    public function reject(Request $request, Dataset $dataset, DatasetCleaningRecommendation $recommendation): JsonResponse
    {
        $this->authorizeDataset($request, $dataset);
        $this->authorizeRecommendation($dataset, $recommendation);

        $recommendation->update(['status' => 'rejected']);

        return response()->json([
            'recommendation' => $this->formatRecommendation($recommendation->fresh()),
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $recommendations
     * @return list<array<string, mixed>>
     */
    private function filterNoOpRecommendations(Dataset $dataset, array $recommendations, DatasetCleaningPreviewer $previewer): array
    {
        return array_values(array_filter($recommendations, function (array $recommendation) use ($dataset, $previewer): bool {
            $steps = $recommendation['suggested_steps'] ?? [];

            if ($steps === []) {
                return false;
            }

            try {
                $preview = $previewer->previewPipeline($dataset, $steps);

                return $preview['will_change_dataset'] === true;
            } catch (\InvalidArgumentException) {
                return false;
            }
        }));
    }

    private function authorizeDataset(Request $request, Dataset $dataset): void
    {
        abort_if($dataset->uploaded_by_id !== $request->user()->id, 404);
    }

    private function authorizeRecommendation(Dataset $dataset, DatasetCleaningRecommendation $recommendation): void
    {
        abort_if($recommendation->dataset_id !== $dataset->id, 404);
    }

    private function formatRecommendation(?DatasetCleaningRecommendation $recommendation): array
    {
        if (! $recommendation) {
            return [];
        }

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
