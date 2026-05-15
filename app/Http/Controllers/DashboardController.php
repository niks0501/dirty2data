<?php

namespace App\Http\Controllers;

use App\Models\Dataset;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        $datasets = Dataset::query()
            ->where('uploaded_by_id', $user->id)
            ->select(['id', 'original_name', 'row_count', 'column_count', 'status', 'created_at', 'size_bytes'])
            ->latest()
            ->get();

        $totalDatasets = $datasets->count();
        $readyDatasets = $datasets->where('status', 'ready')->count();
        $totalRows = $datasets->sum('row_count');
        $totalSizeBytes = $datasets->sum('size_bytes');

        $recentDatasets = $datasets->take(5)->map(fn (Dataset $d) => [
            'id' => $d->id,
            'originalName' => $d->original_name,
            'rowCount' => $d->row_count,
            'columnCount' => $d->column_count,
            'status' => $d->status,
            'createdAt' => $d->created_at->toISOString(),
        ])->values();

        return Inertia::render('dashboard', [
            'stats' => [
                'totalDatasets' => $totalDatasets,
                'readyDatasets' => $readyDatasets,
                'totalRows' => $totalRows,
                'totalSizeBytes' => $totalSizeBytes,
            ],
            'recentDatasets' => $recentDatasets,
        ]);
    }
}
