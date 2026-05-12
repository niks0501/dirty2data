<?php

namespace App\Jobs;

use App\Models\Dataset;
use App\Services\Datasets\DatasetPreviewParser;
use App\Services\Datasets\DatasetProfiler;
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

    public function handle(DatasetPreviewParser $parser, DatasetProfiler $profiler): void
    {
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
}
