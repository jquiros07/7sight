<?php

namespace Tests\Feature\Actions\Video;

use App\Actions\Video\GenerateVideoReport;
use App\Models\AnalysisJob;
use App\Models\User;
use App\Models\Video;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class GenerateVideoReportTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Deliberately does not fake/mock Browsershot or the Node/Chrome process
     * it shells out to - this is the one test in the suite that actually
     * launches a real headless browser, because that's the only way to catch
     * a Browsershot/Chrome launch failure (wrong flags, a $HOME collision,
     * missing dependency, etc). This exact class of bug (crashpad failing to
     * launch) reached production twice because nothing exercised the real
     * render path - see the "Browsershot PDF crashpad" incident.
     */
    public function test_it_renders_a_real_pdf_via_browsershot(): void
    {
        $workspace = Workspace::factory()->create();
        $member = User::factory()->create();
        $this->assignWorkspaceRole($workspace, $member, 'member');
        $video = Video::factory()->create(['workspace_id' => $workspace->id]);

        $pdf = (app(GenerateVideoReport::class))($member, $video);

        $bytes = base64_decode($pdf->base64());

        $this->assertNotEmpty($bytes);
        $this->assertStringStartsWith('%PDF-', $bytes);
    }

    /**
     * Renders a real PDF (see the note above on why this isn't mocked) for a
     * video with a flagged job, to catch a Blade error in the flag markup -
     * e.g. calling ->format() on a null flagged_for_review_at, or referencing
     * flaggedByUser when it wasn't eager-loaded.
     */
    public function test_it_renders_a_flagged_analysis_job_in_the_pdf(): void
    {
        $workspace = Workspace::factory()->create();
        $member = User::factory()->create();
        $this->assignWorkspaceRole($workspace, $member, 'member');
        $video = Video::factory()->create(['workspace_id' => $workspace->id]);
        AnalysisJob::factory()->create([
            'video_id' => $video->id,
            'status' => 'completed',
            'flagged_for_review_at' => now(),
            'flagged_by' => $member->id,
            'flagged_review_note' => 'Missed a weapon at 1:32',
        ]);

        $pdf = (app(GenerateVideoReport::class))($member, $video);

        $bytes = base64_decode($pdf->base64());

        $this->assertNotEmpty($bytes);
        $this->assertStringStartsWith('%PDF-', $bytes);
    }

    public function test_an_outsider_cannot_generate_a_report(): void
    {
        $workspace = Workspace::factory()->create();
        $video = Video::factory()->create(['workspace_id' => $workspace->id]);
        $outsider = User::factory()->create();

        try {
            (app(GenerateVideoReport::class))($outsider, $video);
            $this->fail('Expected a 403 authorization exception.');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
    }
}
