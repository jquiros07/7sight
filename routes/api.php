<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ObjectDetectionCategoryController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\VideoController;
use App\Http\Controllers\WorkspaceController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    Route::get('/dashboard', [DashboardController::class, 'show']);
    Route::post('/search/videos', [SearchController::class, 'videos'])->middleware('throttle:10,1');

    Route::apiResource('workspaces', WorkspaceController::class);
    Route::get('/workspaces/{workspace}/dashboard', [WorkspaceController::class, 'dashboard']);
    Route::apiResource('videos', VideoController::class)->only(['index', 'store', 'show', 'update', 'destroy']);
    Route::post('/videos/{video}/analyze', [VideoController::class, 'analyze'])->middleware('throttle:5,1');
    Route::get('/videos/{video}/stream', [VideoController::class, 'stream']);
    Route::post('/videos/{video}/insights', [VideoController::class, 'insights'])->middleware('throttle:10,1');
    Route::get('/object-detection-categories', [ObjectDetectionCategoryController::class, 'index']);
});

// Called by the analysis-worker (not a browser client) once a video's
// analysis jobs all complete, so it's guarded by a shared secret instead
// of Sanctum.
Route::post('/internal/videos/{video}/insights', [VideoController::class, 'generateInsights'])
    ->middleware('internal.token');
