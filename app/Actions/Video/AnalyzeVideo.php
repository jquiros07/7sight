<?php

namespace App\Actions\Video;

use App\Actions\Video\Concerns\AuthorizesVideoAccess;
use App\Enums\VideoStatus;
use App\Models\AnalysisJob;
use App\Models\User;
use App\Models\Video;
use Illuminate\Support\Facades\Redis;

class AnalyzeVideo
{
    use AuthorizesVideoAccess;

    private const STREAM = 'analysis_jobs';

    /**
     * Queue analysis for a video's configured analysis types. Requires being the
     * uploader or a workspace owner/admin.
     */
    public function __invoke(User $user, Video $video): Video
    {
        $this->authorizeVideoManagement($user, $video);

        $analysisTypes = $video->analysis_types ?? [];

        abort_if(empty($analysisTypes), 422, 'This video has no analysis types configured.');

        $alreadyInProgress = $video->analysisJobs()->whereIn('status', ['pending', 'processing'])->exists();

        abort_if($alreadyInProgress, 422, 'This video already has analysis in progress.');

        foreach ($analysisTypes as $type) {
            $job = AnalysisJob::create([
                'video_id' => $video->id,
                'type' => $type,
                'status' => 'pending',
            ]);

            Redis::connection('analysis_queue')->xadd(self::STREAM, '*', ['job_id' => $job->id]);
        }

        $video->update(['status' => VideoStatus::Processing]);

        return $video->load('analysisJobs');
    }
}
