<?php

namespace App\Actions\Video;

use App\Actions\Workspace\Concerns\AuthorizesWorkspaceAccess;
use App\Models\User;
use App\Models\Video;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ShowVideoThumbnail
{
    use AuthorizesWorkspaceAccess;

    /**
     * Stream a video's generated thumbnail image. Requires workspace
     * membership.
     */
    public function __invoke(User $user, Video $video): BinaryFileResponse
    {
        $this->authorizeMembership($user, $video->workspace);

        abort_unless($video->thumbnail_path, 404);

        return response()->file(Storage::disk($video->disk)->path($video->thumbnail_path));
    }
}
