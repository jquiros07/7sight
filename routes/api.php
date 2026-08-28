<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\ObjectDetectionCategoryController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VideoController;
use App\Http\Controllers\WorkspaceController;
use App\Http\Controllers\WorkspaceMemberController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });
    Route::get('/permissions', [UserController::class, 'permissions']);
    Route::get('/health/detailed', [HealthController::class, 'detailed']);

    Route::get('/dashboard', [DashboardController::class, 'show']);
    Route::get('/dashboard/report', [DashboardController::class, 'report'])->middleware('throttle:10,1');
    Route::post('/search/videos', [SearchController::class, 'videos'])->middleware('throttle:10,1');

    Route::apiResource('workspaces', WorkspaceController::class);
    Route::get('/workspaces/{workspace}/dashboard', [WorkspaceController::class, 'dashboard']);
    Route::get('/workspaces/{workspace}/dashboard/report', [WorkspaceController::class, 'dashboardReport'])->middleware('throttle:10,1');
    Route::post('/workspaces/{workspace}/insight-summary', [WorkspaceController::class, 'generateInsightSummary'])->middleware('throttle:10,1');
    Route::get('/workspaces/{workspace}/members', [WorkspaceMemberController::class, 'index']);
    Route::post('/workspaces/{workspace}/members', [WorkspaceMemberController::class, 'invite']);
    Route::patch('/workspaces/{workspace}/members/{member}', [WorkspaceMemberController::class, 'updateRole']);
    Route::delete('/workspaces/{workspace}/members/{member}', [WorkspaceMemberController::class, 'remove']);
    Route::delete('/workspaces/{workspace}/invitations/{invitation}', [WorkspaceMemberController::class, 'cancelInvitation']);
    Route::apiResource('videos', VideoController::class)->only(['index', 'store', 'show', 'update', 'destroy']);
    Route::post('/videos/{video}/analyze', [VideoController::class, 'analyze'])->middleware('throttle:5,1');
    Route::get('/videos/{video}/stream', [VideoController::class, 'stream']);
    Route::get('/videos/{video}/report', [VideoController::class, 'report'])->middleware('throttle:10,1');
    Route::post('/videos/{video}/insights', [VideoController::class, 'insights'])->middleware('throttle:10,1');
    Route::get('/videos/{video}/inquiries', [VideoController::class, 'inquiries']);
    Route::post('/videos/{video}/inquiries', [VideoController::class, 'inquire'])->middleware('throttle:10,1');
    Route::post('/videos/{video}/analysis-jobs/{analysisJob}/flag', [VideoController::class, 'flagAnalysisJob'])->middleware('throttle:20,1');
    Route::delete('/videos/{video}/analysis-jobs/{analysisJob}/flag', [VideoController::class, 'unflagAnalysisJob']);
    Route::get('/object-detection-categories', [ObjectDetectionCategoryController::class, 'index']);
});

// Called by the analysis-worker (not a browser client) once a video's
// analysis jobs all complete, so it's guarded by a shared secret instead
// of Sanctum.
Route::post('/internal/videos/{video}/insights', [VideoController::class, 'generateInsights'])
    ->middleware('internal.token');
