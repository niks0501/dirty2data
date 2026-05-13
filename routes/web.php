<?php

use App\Http\Controllers\Datasets\CleaningRecipeController;
use App\Http\Controllers\Datasets\DatasetController;
use App\Http\Controllers\Datasets\DatasetUndoController;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

Route::inertia('/', 'welcome', [
    'canRegister' => Features::enabled(Features::registration()),
])->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');

    Route::get('datasets', [DatasetController::class, 'index'])->name('datasets.index');
    Route::post('datasets', [DatasetController::class, 'store'])->name('datasets.store');
    Route::get('datasets/{dataset}', [DatasetController::class, 'show'])->name('datasets.show');
    Route::post('datasets/{dataset}/clean', [DatasetController::class, 'clean'])->name('datasets.clean');
    Route::post('datasets/{dataset}/clean/preview', [DatasetController::class, 'previewClean'])->name('datasets.clean.preview');
    Route::get('datasets/{dataset}/chart', [DatasetController::class, 'chart'])->name('datasets.chart');

    Route::post('datasets/{dataset}/undo', [DatasetUndoController::class, 'undoLast'])->name('datasets.undo');
    Route::post('datasets/{dataset}/undo/{index}', [DatasetUndoController::class, 'undoTo'])->whereNumber('index')->name('datasets.undoTo');
    Route::post('datasets/{dataset}/reset', [DatasetUndoController::class, 'reset'])->name('datasets.reset');

    Route::get('recipes', [CleaningRecipeController::class, 'index'])->name('recipes.index');
    Route::post('datasets/{dataset}/recipes', [CleaningRecipeController::class, 'store'])->name('recipes.store');
    Route::post('datasets/{dataset}/recipes/{recipe}/apply', [CleaningRecipeController::class, 'apply'])->name('recipes.apply');
    Route::delete('recipes/{recipe}', [CleaningRecipeController::class, 'destroy'])->name('recipes.destroy');
});

require __DIR__.'/settings.php';
