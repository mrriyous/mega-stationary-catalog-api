<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\MediaController;
use App\Http\Controllers\Api\SyncController;
use App\Http\Controllers\Api\VideoController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/sync', SyncController::class);
    Route::post('/categories/reorder', [CategoryController::class, 'reorder']);
    Route::apiResource('categories', CategoryController::class);
    Route::apiResource('videos', VideoController::class);
    Route::get('/videos/{video}/download', [MediaController::class, 'video'])->name('videos.download');
    Route::get('/videos/{video}/cover', [MediaController::class, 'cover'])->name('videos.cover');
});
