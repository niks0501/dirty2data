<?php

use App\Http\Controllers\Datasets\DatasetController;
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
});

require __DIR__.'/settings.php';
