<?php

namespace App\Actions\Camera;

use App\Actions\Camera\Concerns\PresentsCameraStatus;
use App\Actions\Camera\Concerns\ResolvesCameraPathName;
use App\Models\Camera;
use App\Models\User;
use App\Support\MediaMtxClient;

class ListCameras
{
    use PresentsCameraStatus;
    use ResolvesCameraPathName;

    public function __construct(
        private readonly MediaMtxClient $mediaMtx,
    ) {}

    /**
     * List cameras in the workspaces the user belongs to, annotated with
     * live status and HLS playback URL.
     *
     * @return array<int, array<string, mixed>>
     */
    public function __invoke(User $user): array
    {
        $activePaths = $this->activePaths($this->mediaMtx);
        $workspaceIds = $user->workspaces()->pluck('workspaces.id');

        return Camera::query()
            ->select(['id', 'workspace_id', 'name', 'location', 'stream_url', 'created_by', 'created_at'])
            ->with([
                'creator:id,name',
                'workspace:id,name',
                'activeRecording:camera_recordings.id,camera_recordings.camera_id,camera_recordings.ends_at',
            ])
            ->whereIn('workspace_id', $workspaceIds)
            ->orderBy('name')
            ->get()
            ->map(fn (Camera $camera) => $this->present($camera, $activePaths))
            ->all();
    }
}
