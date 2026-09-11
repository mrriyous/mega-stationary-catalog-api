<?php

use App\Http\Controllers\SharedCatalogController;
use Illuminate\Support\Facades\Route;

Route::prefix('s/{token}')->group(function () {
    Route::get('/', [SharedCatalogController::class, 'show'])->name('shared-catalog.show');
    Route::get('/videos', [SharedCatalogController::class, 'videos'])->name('shared-catalog.videos');
    Route::get('/videos/{video}', [SharedCatalogController::class, 'detail'])->name('shared-catalog.video');
    Route::get('/videos/{video}/cover', [SharedCatalogController::class, 'cover'])->name('shared-catalog.cover');
    Route::get('/videos/{video}/media', [SharedCatalogController::class, 'media'])->name('shared-catalog.media');
});
