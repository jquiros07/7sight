<?php

namespace App\Actions\Workspace;

use App\Actions\Workspace\Concerns\AuthorizesWorkspaceAccess;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceInquiry;
use Illuminate\Support\Collection;

class ListWorkspaceInquiries
{
    use AuthorizesWorkspaceAccess;

    /**
     * List a workspace's past questions and answers, most recent first.
     * Requires workspace membership.
     *
     * @return Collection<int, WorkspaceInquiry>
     */
    public function __invoke(User $user, Workspace $workspace): Collection
    {
        $this->authorizeMembership($user, $workspace);

        return $workspace->inquiries()->latest()->get();
    }
}
