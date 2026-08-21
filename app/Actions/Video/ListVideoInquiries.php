<?php

namespace App\Actions\Video;

use App\Actions\Workspace\Concerns\AuthorizesWorkspaceAccess;
use App\Models\User;
use App\Models\Video;
use Illuminate\Pagination\LengthAwarePaginator;

class ListVideoInquiries
{
    use AuthorizesWorkspaceAccess;

    /**
     * List a video's past questions and answers, most recent first, paginated
     * so a long history doesn't have to be fetched all at once. Requires
     * workspace membership.
     *
     * @param  array<string, mixed>  $params
     */
    public function __invoke(User $user, Video $video, array $params = []): LengthAwarePaginator
    {
        $this->authorizeMembership($user, $video->workspace);

        $perPage = min((int) ($params['per_page'] ?? 5), 50) ?: 5;

        return $video->inquiries()->latest()->paginate($perPage);
    }
}
