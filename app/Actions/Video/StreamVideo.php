<?php

namespace App\Actions\Video;

use App\Actions\Workspace\Concerns\AuthorizesWorkspaceAccess;
use App\Models\User;
use App\Models\Video;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class StreamVideo
{
    use AuthorizesWorkspaceAccess;

    /**
     * Stream a video's file to the browser. Requires workspace membership.
     * Returns a file response so the browser can issue Range requests for
     * seeking/scrubbing.
     */
    public function __invoke(User $user, Video $video): BinaryFileResponse
    {
        $this->authorizeMembership($user, $video->workspace);

        return response()->file(Storage::disk($video->disk)->path($video->path), [
            'Content-Type' => $video->mime_type ?? 'video/mp4',
        ]);
    }
}
