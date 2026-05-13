<?php

namespace App\Http\Controllers\Datasets;

use App\Http\Controllers\Controller;
use App\Models\CleaningRecipe;
use App\Models\Dataset;
use App\Services\Datasets\DatasetCleaner;
use App\Services\Datasets\DatasetProfiler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CleaningRecipeController extends Controller
{
    /**
     * Save a cleaning recipe from the user's current cleaning operations.
     */
    public function store(Request $request, Dataset $dataset): JsonResponse
    {
        $this->authorizeDataset($request, $dataset);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'steps' => ['required', 'array', 'min:1'],
            'steps.*.operation' => ['required', 'string'],
            'steps.*.column' => ['nullable', 'string'],
            'steps.*.method' => ['nullable', 'string'],
            'steps.*.value' => ['nullable', 'string'],
            'steps.*.target_type' => ['nullable', 'string'],
            'steps.*.text_format' => ['nullable', 'string'],
        ]);

        $recipe = CleaningRecipe::create([
            'user_id' => $request->user()->id,
            'name' => $validated['name'],
            'steps' => $validated['steps'],
        ]);

        return response()->json([
            'recipe' => [
                'id' => $recipe->id,
                'name' => $recipe->name,
                'steps' => $recipe->steps,
                'createdAt' => $recipe->created_at->toISOString(),
            ],
        ]);
    }

    /**
     * List the user's saved recipes.
     */
    public function index(Request $request): JsonResponse
    {
        $recipes = CleaningRecipe::query()
            ->where('user_id', $request->user()->id)
            ->latest()
            ->get()
            ->map(fn (CleaningRecipe $recipe): array => [
                'id' => $recipe->id,
                'name' => $recipe->name,
                'steps' => $recipe->steps,
                'createdAt' => $recipe->created_at->toISOString(),
            ]);

        return response()->json(['recipes' => $recipes]);
    }

    /**
     * Apply a saved recipe to a dataset.
     */
    public function apply(
        Request $request,
        Dataset $dataset,
        CleaningRecipe $recipe,
        DatasetCleaner $cleaner,
        DatasetProfiler $profiler,
    ): JsonResponse {
        $this->authorizeDataset($request, $dataset);

        if ($recipe->user_id !== $request->user()->id) {
            abort(403);
        }

        $results = [];
        $records = $dataset->cleaned_records ?? [];
        $log = $dataset->cleaning_log ?? [];

        foreach ($recipe->steps as $step) {
            try {
                $result = $cleaner->clean($dataset, $step, $records);
                $records = $result['records'];
                $log[] = $result['log'];
                $results[] = [
                    'operation' => $step['operation'],
                    'success' => true,
                    'summary' => $result['log']['summary'] ?? [],
                ];
            } catch (\InvalidArgumentException $e) {
                $results[] = [
                    'operation' => $step['operation'],
                    'success' => false,
                    'error' => $e->getMessage(),
                ];
            }
        }

        $profile = $profiler->profile($records, $dataset->headers ?? []);

        $dataset->update([
            'cleaned_records' => $records,
            'row_count' => count($records),
            'profile' => $profile,
            'cleaning_log' => $log,
        ]);

        return response()->json([
            'results' => $results,
            'rowsBefore' => count($dataset->cleaned_records ?? []) + 0,
            'rowsAfter' => count($records),
        ]);
    }

    /**
     * Delete a saved recipe.
     */
    public function destroy(Request $request, CleaningRecipe $recipe): JsonResponse
    {
        if ($recipe->user_id !== $request->user()->id) {
            abort(403);
        }

        $recipe->delete();

        return response()->json(['deleted' => true]);
    }

    private function authorizeDataset(Request $request, Dataset $dataset): void
    {
        abort_if($dataset->uploaded_by_id !== $request->user()->id, 404);
    }
}
