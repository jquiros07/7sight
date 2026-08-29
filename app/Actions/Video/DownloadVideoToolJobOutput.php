<?php

namespace App\Actions\Video;

use App\Actions\Workspace\Concerns\AuthorizesWorkspaceAccess;
use App\Models\User;
use App\Models\Video;
use App\Models\VideoToolJob;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DownloadVideoToolJobOutput
{
    use AuthorizesWorkspaceAccess;

    /**
     * Download a completed video tool job's output file. Requires workspace
     * membership.
     */
    public function __invoke(User $user, Video $video, VideoToolJob $videoToolJob): BinaryFileResponse
    {
        $this->authorizeMembership($user, $video->workspace);

        abort_unless($videoToolJob->video_id === $video->id, 404);
        abort_unless($videoToolJob->status === 'completed', 404, 'Output is not ready.');

        return response()->download(
            Storage::disk($videoToolJob->output_disk)->path($videoToolJob->output_path)
        );
    }
}
