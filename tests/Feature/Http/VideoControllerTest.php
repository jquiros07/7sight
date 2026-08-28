<?php

namespace Tests\Feature\Http;

use App\Models\User;
use App\Models\Video;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Exercises the real HTTP route/controller layer - Action-level tests alone
 * wouldn't catch a controller-level mistake building the explicit field
 * array now that $request->all() is gone.
 */
class VideoControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_partial_update_over_http_does_not_wipe_omitted_fields(): void
    {
        $workspace = Workspace::factory()->create();
        $user = User::factory()->create();
        $this->assignWorkspaceRole($workspace, $user, 'member');
        $video = Video::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'title' => 'Old Title',
            'description' => 'Original description',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->patchJson("/api/videos/{$video->id}", ['title' => 'New Title']);

        $response->assertOk();
        $this->assertSame('New Title', $video->fresh()->title);
        $this->assertSame('Original description', $video->fresh()->description);
    }
}
