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
     * live status and HLS playback URL. Pass $workspaceId to narrow this to
     * a single workspace - it's intersected with the user's own workspace
     * ids below, so requesting one the user doesn't belong to just yields
     * an empty list rather than leaking another workspace's cameras.
     *
     * @return array<int, array<string, mixed>>
     */
    public function __invoke(User $user, ?int $workspaceId = null): array
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
            ->when($workspaceId, fn ($query, $workspaceId) => $query->where('workspace_id', $workspaceId))
            ->orderBy('name')
            ->get()
            ->map(fn (Camera $camera) => $this->present($user, $camera, $activePaths))
            ->all();
    }
}
