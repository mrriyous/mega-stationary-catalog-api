<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CatalogShareController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\ClientErrorController;
use App\Http\Controllers\Api\MediaController;
use App\Http\Controllers\Api\SyncBootstrapController;
use App\Http\Controllers\Api\SyncController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\VideoController;
use App\Http\Controllers\Api\VideoSortController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/sync', SyncController::class);
    Route::get('/sync/bootstrap', SyncBootstrapController::class);
    Route::post('/catalog-shares', [CatalogShareController::class, 'store'])->middleware('throttle:20,1');
    Route::post('/client-errors', ClientErrorController::class)->middleware('throttle:20,1');

    Route::get('/categories', [CategoryController::class, 'index']);
    Route::get('/categories/{category}', [CategoryController::class, 'show']);

    Route::get('/videos', [VideoController::class, 'index']);
    Route::get('/videos/{video}', [VideoController::class, 'show']);
    Route::get('/videos/{video}/download', [MediaController::class, 'video'])->name('videos.download');
    Route::get('/videos/{video}/cover', [MediaController::class, 'cover'])->name('videos.cover');

    Route::middleware('admin')->group(function () {
        Route::post('/categories', [CategoryController::class, 'store']);
        Route::post('/categories/reorder', [CategoryController::class, 'reorder']);
        Route::put('/categories/{category}', [CategoryController::class, 'update']);
        Route::patch('/categories/{category}', [CategoryController::class, 'update']);
        Route::delete('/categories/{category}', [CategoryController::class, 'destroy']);
        Route::get('/categories/{category}/videos/order', [VideoSortController::class, 'index']);
        Route::post('/categories/{category}/videos/reorder', [VideoSortController::class, 'update']);

        Route::post('/videos', [VideoController::class, 'store']);
        Route::put('/videos/{video}', [VideoController::class, 'update']);
        Route::patch('/videos/{video}', [VideoController::class, 'update']);
        Route::delete('/videos/{video}', [VideoController::class, 'destroy']);

        Route::get('/users', [UserController::class, 'index']);
        Route::post('/users', [UserController::class, 'store']);
        Route::put('/users/{user}', [UserController::class, 'update']);
        Route::patch('/users/{user}', [UserController::class, 'update']);
        Route::delete('/users/{user}', [UserController::class, 'destroy']);
    });
});
