<?php

namespace App\Jobs;

use App\Models\Dataset;
use App\Models\DatasetQualityScore;
use App\Services\Datasets\DatasetPreviewParser;
use App\Services\Datasets\DatasetProfiler;
use App\Services\Datasets\DatasetQualityScoreClient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ProcessDatasetUpload implements ShouldQueue
{
    use Queueable;

    public int $timeout = 600;

    public function __construct(private readonly Dataset $dataset) {}

    public function handle(
        DatasetPreviewParser $parser,
        DatasetProfiler $profiler,
        DatasetQualityScoreClient $scoreClient,
    ): void {
        $dataset = $this->dataset;

        $dataset->update([
            'status' => 'processing',
            'processing_started_at' => now(),
            'processing_progress' => 0,
            'processing_rows_processed' => 0,
            'processing_error' => null,
        ]);

        try {
            $filePath = Storage::disk('local')->path($dataset->disk_path);

            if (! is_file($filePath) || ! is_readable($filePath)) {
                throw new \RuntimeException('The uploaded file could not be found on disk.');
            }

            $parsed = $parser->parseChunked(
                $filePath,
                $dataset->extension,
                function (int $rowsProcessed, int $estimatedTotal) use ($dataset): void {
                    $progress = $estimatedTotal > 0
                        ? min((int) round(($rowsProcessed / $estimatedTotal) * 95), 95)
                        : 0;

                    $dataset->update([
                        'processing_rows_processed' => $rowsProcessed,
                        'processing_progress' => $progress,
                    ]);
                },
            );

            $profile = $profiler->profile($parsed['records'], $parsed['headers']);

            $previewLimit = min($parsed['row_count'], 5000);
            $previewRecords = array_slice($parsed['records'], 0, $previewLimit);

            $dataset->update([
                'headers' => $parsed['headers'],
                'original_records' => $previewRecords,
                'cleaned_records' => $previewRecords,
                'row_count' => $parsed['row_count'],
                'column_count' => $parsed['column_count'],
                'preview' => [
                    'headers' => $parsed['headers'],
                    'sample_rows' => array_slice($previewRecords, 0, 15),
                    'row_count' => $parsed['row_count'],
                    'column_count' => $parsed['column_count'],
                    'preview_note' => $parsed['row_count'] > 5000
                        ? 'Showing first 5,000 rows of '.number_format($parsed['row_count']).'.'
                        : null,
                ],
                'profile' => $profile,
                'cleaning_log' => [],
                'status' => 'ready',
                'processing_finished_at' => now(),
                'processing_progress' => 100,
                'processing_error' => null,
            ]);

            $this->requestQualityScore($dataset, $scoreClient);
        } catch (Throwable $e) {
            Log::error('Dataset processing failed', [
                'dataset_id' => $dataset->id,
                'error' => $e->getMessage(),
            ]);

            $dataset->update([
                'status' => 'failed',
                'processing_finished_at' => now(),
                'processing_error' => $e->getMessage(),
                'processing_progress' => 0,
            ]);

            throw $e;
        }
    }

    /**
     * Call the Python data processing service to calculate and persist the quality score.
     *
     * Errors are logged at warning level and do not fail the job —
     * the dataset stays usable even if the Python service is temporarily unavailable.
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
        } catch (Throwable $e) {
            Log::warning('Quality score calculation skipped during background processing.', [
                'dataset_id' => $dataset->id,
                'reason' => $e->getMessage(),
            ]);
        }
    }
}
