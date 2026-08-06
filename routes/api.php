<?php

use App\Http\Controllers\WorkspaceController;
use App\Models\Video;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    Route::get('/dashboard-stats', function (Request $request) {
        $workspaceIds = $request->user()->workspaces()->pluck('workspaces.id');

        return [
            'workspaces_count' => $workspaceIds->count(),
            'videos_count' => Video::whereIn('workspace_id', $workspaceIds)->count(),
        ];
    });

    Route::apiResource('workspaces', WorkspaceController::class);
});
