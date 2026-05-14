<?php

namespace App\Http\Controllers\Datasets;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessAfterQualityScore;
use App\Models\Dataset;
use App\Services\Datasets\DatasetProfiler;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DatasetUndoController extends Controller
{
    public function undoLast(
        Request $request,
        Dataset $dataset,
        DatasetProfiler $profiler,
    ): RedirectResponse {
        $this->authorizeDataset($request, $dataset);

        $snapshots = $dataset->cleaning_snapshots ?? [];

        if ($snapshots === []) {
            return back()->with('toast', [
                'type' => 'error',
                'message' => __('Nothing to undo. No previous states are saved.'),
            ]);
        }

        $previousSnapshot = array_shift($snapshots);
        $previousState = $this->snapshotRecords($previousSnapshot);
        $headers = $this->snapshotHeaders($previousSnapshot, $dataset->headers ?? []);
        $log = $dataset->cleaning_log ?? [];

        if ($log !== []) {
            array_pop($log);
        }

        $profile = $profiler->profile($previousState, $headers);

        $dataset->update([
            'headers' => $headers,
            'cleaned_records' => $previousState,
            'row_count' => count($previousState),
            'column_count' => count($headers),
            'cleaning_snapshots' => array_values($snapshots),
            'cleaning_log' => $log,
            'profile' => $profile,
        ]);

        ProcessAfterQualityScore::dispatch($dataset, $profile)->afterResponse();

        return to_route('datasets.show', ['dataset' => $dataset])->with('toast', [
            'type' => 'success',
            'message' => __('The last cleaning operation has been undone.'),
        ]);
    }

    public function undoTo(
        Request $request,
        Dataset $dataset,
        DatasetProfiler $profiler,
        int $index,
    ): RedirectResponse {
        $this->authorizeDataset($request, $dataset);

        $snapshots = $dataset->cleaning_snapshots ?? [];

        if (! isset($snapshots[$index])) {
            return back()->with('toast', [
                'type' => 'error',
                'message' => __('The selected undo point is not available.'),
            ]);
        }

        $targetSnapshot = $snapshots[$index];
        $targetState = $this->snapshotRecords($targetSnapshot);
        $headers = $this->snapshotHeaders($targetSnapshot, $dataset->headers ?? []);
        $log = $dataset->cleaning_log ?? [];
        $logCount = count($log);
        $snapshotIndex = $index;

        $keptLog = array_slice($log, 0, $logCount - $index);
        $keptSnapshots = array_slice($snapshots, $index + 1);

        $profile = $profiler->profile($targetState, $headers);

        $dataset->update([
            'headers' => $headers,
            'cleaned_records' => $targetState,
            'row_count' => count($targetState),
            'column_count' => count($headers),
            'cleaning_snapshots' => array_values($keptSnapshots),
            'cleaning_log' => $keptLog,
            'profile' => $profile,
        ]);

        ProcessAfterQualityScore::dispatch($dataset, $profile)->afterResponse();

        return to_route('datasets.show', ['dataset' => $dataset])->with('toast', [
            'type' => 'success',
            'message' => __('Dataset has been restored to the selected point.'),
        ]);
    }

    public function reset(
        Request $request,
        Dataset $dataset,
        DatasetProfiler $profiler,
    ): RedirectResponse {
        $this->authorizeDataset($request, $dataset);

        $originalRecords = $dataset->original_records ?? [];
        $headers = $this->originalHeaders($dataset, $originalRecords);
        $profile = $profiler->profile($originalRecords, $headers);

        $dataset->update([
            'headers' => $headers,
            'cleaned_records' => $originalRecords,
            'row_count' => count($originalRecords),
            'column_count' => count($headers),
            'cleaning_snapshots' => [],
            'cleaning_log' => [],
            'profile' => $profile,
        ]);

        ProcessAfterQualityScore::dispatch($dataset, $profile)->afterResponse();

        return to_route('datasets.show', ['dataset' => $dataset])->with('toast', [
            'type' => 'success',
            'message' => __('Dataset has been reset to its original state. All cleaning operations have been undone.'),
        ]);
    }

    private function authorizeDataset(Request $request, Dataset $dataset): void
    {
        abort_if($dataset->uploaded_by_id !== $request->user()->id, 404);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function snapshotRecords(mixed $snapshot): array
    {
        if (is_array($snapshot) && isset($snapshot['records']) && is_array($snapshot['records'])) {
            return $snapshot['records'];
        }

        return is_array($snapshot) ? $snapshot : [];
    }

    /**
     * @param  list<string>  $fallback
     * @return list<string>
     */
    private function snapshotHeaders(mixed $snapshot, array $fallback): array
    {
        if (is_array($snapshot) && isset($snapshot['headers']) && is_array($snapshot['headers'])) {
            return array_values($snapshot['headers']);
        }

        return $fallback;
    }

    /**
     * @param  list<array<string, mixed>>  $originalRecords
     * @return list<string>
     */
    private function originalHeaders(Dataset $dataset, array $originalRecords): array
    {
        if ($originalRecords !== []) {
            return array_keys($originalRecords[0]);
        }

        return $dataset->preview['headers'] ?? ($dataset->headers ?? []);
    }
}
