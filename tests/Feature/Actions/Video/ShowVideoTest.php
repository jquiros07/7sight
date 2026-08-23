<?php

namespace Tests\Feature\Actions\Video;

use App\Actions\Video\ShowVideo;
use App\Models\User;
use App\Models\Video;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class ShowVideoTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_workspace_member_can_view_a_video(): void
    {
        $workspace = Workspace::factory()->create();
        $member = User::factory()->create();
        $workspace->users()->attach($member->id, ['role' => 'member']);
        $video = Video::factory()->create(['workspace_id' => $workspace->id]);

        $result = (new ShowVideo)($member, $video);

        $this->assertSame($video->id, $result->id);
        $this->assertTrue($result->relationLoaded('analysisJobs'));
        $this->assertTrue($result->relationLoaded('latestInsight'));
    }

    public function test_an_outsider_cannot_view_the_video(): void
    {
        $workspace = Workspace::factory()->create();
        $video = Video::factory()->create(['workspace_id' => $workspace->id]);
        $outsider = User::factory()->create();

        try {
            (new ShowVideo)($outsider, $video);
            $this->fail('Expected a 403 authorization exception.');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
    }
}
