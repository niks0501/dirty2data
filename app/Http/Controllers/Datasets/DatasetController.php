<?php

namespace App\Http\Controllers\Datasets;

use App\Http\Controllers\Controller;
use App\Http\Requests\Datasets\StoreDatasetRequest;
use App\Models\Dataset;
use App\Services\Datasets\DatasetPreviewParser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class DatasetController extends Controller
{
    /**
     * Display the dataset upload page.
     */
    public function index(): Response
    {
        return Inertia::render('datasets/index');
    }

    /**
     * Store a newly uploaded dataset file.
     */
    public function store(StoreDatasetRequest $request, DatasetPreviewParser $parser): RedirectResponse
    {
        $file = $request->file('dataset_file');
        $extension = $file->getClientOriginalExtension();
        $user = $request->user();
        $uuidFilename = Str::uuid().'.'.$extension;

        $storedPath = Storage::disk('local')->putFileAs(
            'datasets/'.$user->id,
            $file,
            $uuidFilename,
        );

        $parsed = $parser->parse($file->getRealPath());

        $dataset = Dataset::create([
            'uploaded_by_id' => $user->id,
            'original_name' => $file->getClientOriginalName(),
            'disk_path' => $storedPath,
            'mime_type' => $file->getMimeType(),
            'size_bytes' => $file->getSize(),
            'preview' => $parsed,
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Dataset uploaded successfully.'),
        ]);

        return to_route('datasets.show', [
            'dataset' => $dataset->id,
        ]);
    }

    /**
     * Display the dataset preview page.
     */
    public function show(Request $request, Dataset $dataset): Response
    {
        abort_if($dataset->uploaded_by_id !== $request->user()->id, 404);

        return Inertia::render('datasets/show', [
            'dataset' => [
                'id' => $dataset->id,
                'originalName' => $dataset->original_name,
                'mimeType' => $dataset->mime_type,
                'sizeBytes' => $dataset->size_bytes,
                'rowCount' => $dataset->preview['row_count'] ?? 0,
                'columnCount' => $dataset->preview['column_count'] ?? 0,
                'headers' => $dataset->preview['headers'] ?? [],
                'previewRows' => $dataset->preview['sample_rows'] ?? [],
                'createdAt' => $dataset->created_at->toISOString(),
            ],
        ]);
    }
}
