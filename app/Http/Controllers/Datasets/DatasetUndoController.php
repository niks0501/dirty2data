<?php

namespace App\Http\Controllers\Datasets;

use App\Http\Controllers\Controller;
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

        $previousState = array_shift($snapshots);
        $log = $dataset->cleaning_log ?? [];

        if ($log !== []) {
            array_pop($log);
        }

        $profile = $profiler->profile($previousState, $dataset->headers ?? []);

        $dataset->update([
            'cleaned_records' => $previousState,
            'row_count' => count($previousState),
            'cleaning_snapshots' => array_values($snapshots),
            'cleaning_log' => $log,
            'profile' => $profile,
        ]);

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

        $targetState = $snapshots[$index];
        $log = $dataset->cleaning_log ?? [];
        $logCount = count($log);
        $snapshotIndex = $index;

        // Snapshot[0] is the state BEFORE log[0] was applied,
        // Snapshot[1] is before log[1], etc.
        // Undo to snapshot[$index] means keeping log entries [0..($index-1)]
        // and discarding log entries [$index..end]
        $keptLog = array_slice($log, 0, $snapshotIndex);
        $keptSnapshots = array_slice($snapshots, $index + 1);

        $profile = $profiler->profile($targetState, $dataset->headers ?? []);

        $dataset->update([
            'cleaned_records' => $targetState,
            'row_count' => count($targetState),
            'cleaning_snapshots' => array_values($keptSnapshots),
            'cleaning_log' => $keptLog,
            'profile' => $profile,
        ]);

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
        $profile = $profiler->profile($originalRecords, $dataset->headers ?? []);

        $dataset->update([
            'cleaned_records' => $originalRecords,
            'row_count' => count($originalRecords),
            'cleaning_snapshots' => [],
            'cleaning_log' => [],
            'profile' => $profile,
        ]);

        return to_route('datasets.show', ['dataset' => $dataset])->with('toast', [
            'type' => 'success',
            'message' => __('Dataset has been reset to its original state. All cleaning operations have been undone.'),
        ]);
    }

    private function authorizeDataset(Request $request, Dataset $dataset): void
    {
        abort_if($dataset->uploaded_by_id !== $request->user()->id, 404);
    }
}
