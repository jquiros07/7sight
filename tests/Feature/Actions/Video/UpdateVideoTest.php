<?php

namespace Tests\Feature\Actions\Video;

use App\Actions\Video\UpdateVideo;
use App\Enums\AnalysisType;
use App\Enums\VideoStatus;
use App\Models\User;
use App\Models\Video;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class UpdateVideoTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_uploader_can_update_their_own_video(): void
    {
        $workspace = Workspace::factory()->create();
        $uploader = User::factory()->create();
        $this->assignWorkspaceRole($workspace, $uploader, 'member');
        $video = Video::factory()->create(['workspace_id' => $workspace->id, 'user_id' => $uploader->id, 'title' => 'Old Title']);

        $updated = (new UpdateVideo)($uploader, $video, ['title' => 'New Title']);

        $this->assertSame('New Title', $updated->title);
    }

    public function test_an_admin_can_update_another_users_video(): void
    {
        $workspace = Workspace::factory()->create();
        $admin = User::factory()->create();
        $this->assignWorkspaceRole($workspace, $admin, 'admin');
        $uploader = User::factory()->create();
        $this->assignWorkspaceRole($workspace, $uploader, 'member');
        $video = Video::factory()->create(['workspace_id' => $workspace->id, 'user_id' => $uploader->id]);

        $updated = (new UpdateVideo)($admin, $video, ['description' => 'Updated by admin']);

        $this->assertSame('Updated by admin', $updated->description);
    }

    public function test_a_plain_member_cannot_update_another_users_video(): void
    {
        $workspace = Workspace::factory()->create();
        $member = User::factory()->create();
        $this->assignWorkspaceRole($workspace, $member, 'member');
        $uploader = User::factory()->create();
        $this->assignWorkspaceRole($workspace, $uploader, 'member');
        $video = Video::factory()->create(['workspace_id' => $workspace->id, 'user_id' => $uploader->id]);

        try {
            (new UpdateVideo)($member, $video, ['title' => 'Hijacked']);
            $this->fail('Expected a 403 authorization exception.');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
    }

    public function test_changing_the_analysis_types_resets_the_status_to_uploaded(): void
    {
        $workspace = Workspace::factory()->create();
        $uploader = User::factory()->create();
        $this->assignWorkspaceRole($workspace, $uploader, 'member');
        $video = Video::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $uploader->id,
            'status' => VideoStatus::Ready,
            'analysis_types' => [AnalysisType::ThreatDetection->value],
        ]);

        $updated = (new UpdateVideo)($uploader, $video, [
            'analysis_types' => [AnalysisType::ThreatDetection->value, AnalysisType::TextDetection->value],
        ]);

        $this->assertSame(VideoStatus::Uploaded, $updated->status);
    }

    public function test_it_does_not_reset_the_status_while_analysis_is_processing(): void
    {
        $workspace = Workspace::factory()->create();
        $uploader = User::factory()->create();
        $this->assignWorkspaceRole($workspace, $uploader, 'member');
        $video = Video::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $uploader->id,
            'status' => VideoStatus::Processing,
            'analysis_types' => [AnalysisType::ThreatDetection->value],
        ]);

        $updated = (new UpdateVideo)($uploader, $video, [
            'analysis_types' => [AnalysisType::ThreatDetection->value, AnalysisType::TextDetection->value],
        ]);

        $this->assertSame(VideoStatus::Processing, $updated->status);
    }

    public function test_it_does_not_reset_the_status_when_the_type_list_is_unchanged(): void
    {
        $workspace = Workspace::factory()->create();
        $uploader = User::factory()->create();
        $this->assignWorkspaceRole($workspace, $uploader, 'member');
        $video = Video::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $uploader->id,
            'status' => VideoStatus::Ready,
            'analysis_types' => [AnalysisType::ThreatDetection->value],
        ]);

        $updated = (new UpdateVideo)($uploader, $video, [
            'analysis_types' => [AnalysisType::ThreatDetection->value],
        ]);

        $this->assertSame(VideoStatus::Ready, $updated->status);
    }

    public function test_it_rejects_invalid_input(): void
    {
        $workspace = Workspace::factory()->create();
        $uploader = User::factory()->create();
        $this->assignWorkspaceRole($workspace, $uploader, 'member');
        $video = Video::factory()->create(['workspace_id' => $workspace->id, 'user_id' => $uploader->id]);

        $this->expectException(ValidationException::class);

        (new UpdateVideo)($uploader, $video, ['title' => '']);
    }
}
