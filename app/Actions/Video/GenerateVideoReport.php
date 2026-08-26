<?php

namespace App\Actions\Video;

use App\Actions\Workspace\Concerns\AuthorizesWorkspaceAccess;
use App\Models\AnalysisJob;
use App\Models\User;
use App\Models\Video;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Spatie\LaravelPdf\Facades\Pdf;
use Spatie\LaravelPdf\PdfBuilder;

class GenerateVideoReport
{
    use AuthorizesWorkspaceAccess;

    /**
     * Render a downloadable PDF summary of a video's analysis, AI insights,
     * and Inquire history - the same data the video results page shows.
     * Requires workspace membership.
     */
    public function __invoke(User $user, Video $video): PdfBuilder
    {
        $this->authorizeMembership($user, $video->workspace);

        $video->loadMissing('analysisJobs.results', 'analysisJobs.flaggedByUser', 'latestInsight', 'workspace', 'inquiries');

        return Pdf::view('pdfs.video-report', [
            'video' => $video,
            'jobs' => $this->latestJobsByType($video),
            'insights' => $video->latestInsight,
            'inquiries' => $video->inquiries->sortByDesc('created_at')->values(),
            'generatedAt' => now(),
        ])
            ->format('a4')
            ->margins(12, 12, 12, 12)
            ->download($this->fileName($video));
    }

    /**
     * The most recent job per analysis type - an older, superseded attempt
     * from a retry is dropped, same rule the video's overall status and the
     * results page use.
     *
     * @return Collection<int, AnalysisJob>
     */
    private function latestJobsByType(Video $video): Collection
    {
        return $video->analysisJobs
            ->groupBy(fn (AnalysisJob $job) => $job->type->value)
            ->map(fn (Collection $jobs) => $jobs->sortByDesc('created_at')->first())
            ->sortByDesc(fn (AnalysisJob $job) => $job->created_at)
            ->values();
    }

    private function fileName(Video $video): string
    {
        $slug = Str::slug($video->title) ?: 'video';

        return "{$slug}-report.pdf";
    }
}
