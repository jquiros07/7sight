<?php

namespace Tests\Feature\Actions\Video;

use App\Actions\Video\DeleteVideo;
use App\Models\User;
use App\Models\Video;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class DeleteVideoTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_uploader_can_delete_their_own_video(): void
    {
        $workspace = Workspace::factory()->create();
        $uploader = User::factory()->create();
        $workspace->users()->attach($uploader->id, ['role' => 'member']);
        $video = Video::factory()->create(['workspace_id' => $workspace->id, 'user_id' => $uploader->id]);

        (new DeleteVideo)($uploader, $video);

        $this->assertSoftDeleted($video);
    }

    public function test_the_workspace_owner_can_delete_another_users_video(): void
    {
        $owner = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $workspace->users()->attach($owner->id, ['role' => 'owner']);
        $uploader = User::factory()->create();
        $workspace->users()->attach($uploader->id, ['role' => 'member']);
        $video = Video::factory()->create(['workspace_id' => $workspace->id, 'user_id' => $uploader->id]);

        (new DeleteVideo)($owner, $video);

        $this->assertSoftDeleted($video);
    }

    public function test_a_workspace_admin_can_delete_another_users_video(): void
    {
        $workspace = Workspace::factory()->create();
        $admin = User::factory()->create();
        $workspace->users()->attach($admin->id, ['role' => 'admin']);
        $uploader = User::factory()->create();
        $workspace->users()->attach($uploader->id, ['role' => 'member']);
        $video = Video::factory()->create(['workspace_id' => $workspace->id, 'user_id' => $uploader->id]);

        (new DeleteVideo)($admin, $video);

        $this->assertSoftDeleted($video);
    }

    public function test_a_plain_member_cannot_delete_another_users_video(): void
    {
        $workspace = Workspace::factory()->create();
        $member = User::factory()->create();
        $workspace->users()->attach($member->id, ['role' => 'member']);
        $uploader = User::factory()->create();
        $workspace->users()->attach($uploader->id, ['role' => 'member']);
        $video = Video::factory()->create(['workspace_id' => $workspace->id, 'user_id' => $uploader->id]);

        try {
            (new DeleteVideo)($member, $video);
            $this->fail('Expected a 403 authorization exception.');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }

        $this->assertNotSoftDeleted($video);
    }

    public function test_an_outsider_cannot_delete_the_video(): void
    {
        $workspace = Workspace::factory()->create();
        $uploader = User::factory()->create();
        $workspace->users()->attach($uploader->id, ['role' => 'member']);
        $video = Video::factory()->create(['workspace_id' => $workspace->id, 'user_id' => $uploader->id]);
        $outsider = User::factory()->create();

        try {
            (new DeleteVideo)($outsider, $video);
            $this->fail('Expected a 403 authorization exception.');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }

        $this->assertNotSoftDeleted($video);
    }
}
