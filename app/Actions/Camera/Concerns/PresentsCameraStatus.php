<?php

namespace App\Actions\Camera\Concerns;

use App\Actions\Workspace\Concerns\AuthorizesWorkspaceAccess;
use App\Models\Camera;
use App\Models\User;
use App\Support\MediaMtxClient;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Requires ResolvesCameraPathName and a MediaMtxClient $this->mediaMtx property.
 */
trait PresentsCameraStatus
{
    use AuthorizesWorkspaceAccess;

    /**
     * @return array<string, bool>
     */
    private function activePaths(MediaMtxClient $mediaMtx): array
    {
        try {
            return $mediaMtx->listActivePaths();
        } catch (Throwable $e) {
            Log::error('Failed to fetch MediaMTX path statuses', ['exception' => $e]);

            return [];
        }
    }

    /**
     * @param  array<string, bool>  $activePaths
     * @return array<string, mixed>
     */
    private function present(User $user, Camera $camera, array $activePaths): array
    {
        $canViewCredentials = $this->userHasPermission($user, $camera->workspace, 'cameras.view-credentials');

        return [
            'id' => $camera->id,
            'name' => $camera->name,
            'location' => $camera->location,
            'stream_url' => $canViewCredentials ? $camera->stream_url : $this->maskCredentials($camera->stream_url),
            'workspace_id' => $camera->workspace_id,
            'workspace' => $camera->workspace?->name,
            'created_by' => $camera->creator?->name,
            'created_at' => $camera->created_at,
            'is_live' => $activePaths[$this->pathName($camera)] ?? false,
            'hls_url' => route('cameras.hls', ['camera' => $camera->id, 'path' => 'index.m3u8']),
            'active_recording_ends_at' => $camera->activeRecording?->ends_at,
        ];
    }

    /**
     * Hides embedded RTSP userinfo credentials (rtsp://user:pass@host/...)
     * while keeping the host/path visible for identifying the camera.
     */
    private function maskCredentials(string $streamUrl): string
    {
        return preg_replace('/^(rtsp:\/\/)[^@\/]+@/i', '$1***:***@', $streamUrl) ?? $streamUrl;
    }
}
