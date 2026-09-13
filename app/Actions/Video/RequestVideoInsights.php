<?php

namespace App\Actions\Video;

use App\Enums\AnalysisType;
use App\Jobs\GenerateVideoInsightsJob;
use App\Models\User;
use App\Models\Video;

class RequestVideoInsights
{
    public function __construct(
        private readonly GenerateVideoInsights $generateVideoInsights,
    ) {}

    /**
     * Queue AI insight generation for the video as the requesting user.
     * Runs every pre-flight check synchronously (authorization, completed
     * analysis exists, not already up to date) via
     * GenerateVideoInsights::assertCanGenerate() - the same check the job
     * itself re-runs before actually calling the AI - so an invalid request
     * fails immediately with a specific message instead of surfacing later
     * as a generic background job failure.
     */
    public function __invoke(User $user, Video $video, ?AnalysisType $type = null): void
    {
        $this->generateVideoInsights->assertCanGenerate($user, $video, $type);

        GenerateVideoInsightsJob::dispatch($video, $user, $type);
    }
}
