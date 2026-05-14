<?php

namespace App\Jobs;

use App\Models\Dataset;
use App\Models\DatasetQualityScore;
use App\Services\Datasets\DatasetQualityScoreClient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessAfterQualityScore implements ShouldQueue
{
    use Queueable;

    public int $timeout = 300;

    public function __construct(
        private readonly Dataset $dataset,
        private readonly array $profileMetrics,
    ) {}

    public function handle(DatasetQualityScoreClient $scoreClient): void
    {
        try {
            $result = $scoreClient->scoreFromMetrics($this->profileMetrics, 'after');
        } catch (Throwable $e) {
            Log::warning('After-quality score calculation skipped.', [
                'dataset_id' => $this->dataset->id,
                'reason' => $e->getMessage(),
            ]);

            return;
        }

        $pm = $result['profile_metrics'] ?? [];
        $bd = $result['breakdown'] ?? [];

        DatasetQualityScore::create([
            'dataset_id' => $this->dataset->id,
            'score_type' => 'after',
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
    }

    /**
     * Handle a job failure.
     */
    public function failed(?Throwable $e): void
    {
        Log::warning('After-quality score calculation failed.', [
            'dataset_id' => $this->dataset->id,
            'reason' => $e?->getMessage(),
        ]);
    }
}
