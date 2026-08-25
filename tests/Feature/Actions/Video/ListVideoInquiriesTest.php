<?php

namespace Tests\Feature\Actions\Video;

use App\Actions\Video\ListVideoInquiries;
use App\Models\User;
use App\Models\Video;
use App\Models\VideoInquiry;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class ListVideoInquiriesTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_workspace_member_can_list_a_videos_inquiries(): void
    {
        $workspace = Workspace::factory()->create();
        $member = User::factory()->create();
        $this->assignWorkspaceRole($workspace, $member, 'member');
        $video = Video::factory()->create(['workspace_id' => $workspace->id]);
        VideoInquiry::create([
            'video_id' => $video->id,
            'user_id' => $member->id,
            'question' => 'What happens in this video?',
            'answer' => ['answerable' => true, 'answer' => 'Nothing notable.', 'confidence' => 80, 'evidence' => [], 'caveats' => null],
        ]);

        $results = (app(ListVideoInquiries::class))($member, $video);

        $this->assertSame(1, $results->total());
    }

    public function test_an_outsider_cannot_list_a_videos_inquiries(): void
    {
        $workspace = Workspace::factory()->create();
        $video = Video::factory()->create(['workspace_id' => $workspace->id]);
        $outsider = User::factory()->create();

        try {
            (app(ListVideoInquiries::class))($outsider, $video);
            $this->fail('Expected a 403 authorization exception.');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
    }
}
