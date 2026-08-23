<?php

namespace App\Actions\Video;

use App\Actions\Video\Concerns\AuthorizesVideoAccess;
use App\Enums\AnalysisType;
use App\Enums\VideoStatus;
use App\Models\AnalysisJob;
use App\Models\User;
use App\Models\Video;
use Illuminate\Support\Facades\Redis;
use Sentry\Tracing\SpanContext;

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

        $pendingTypes = $this->typesNeedingAnalysis($video, $analysisTypes);

        abort_if(empty($pendingTypes), 422, 'This video has already been analyzed with the current settings.');

        foreach ($pendingTypes as $type) {
            $job = AnalysisJob::create([
                'video_id' => $video->id,
                'type' => $type,
                'status' => 'pending',
            ]);

            $this->publish($job);
        }

        $video->update(['status' => VideoStatus::Processing]);

        return $video->load('analysisJobs');
    }

    /**
     * Push an analysis job onto the Redis Stream the Python worker consumes,
     * wrapped in a Sentry `queue.publish` span so it shows up in Sentry's
     * Queues dashboard. The current span's trace/baggage headers are
     * attached to the message so the worker's `queue.process` span
     * continues this same distributed trace instead of starting a new one.
     */
    private function publish(AnalysisJob $job): void
    {
        \Sentry\trace(function () use ($job) {
            Redis::connection('analysis_queue')->xadd(self::STREAM, '*', [
                'job_id' => $job->id,
                'sentry_trace' => \Sentry\getTraceparent(),
                'baggage' => \Sentry\getBaggage(),
            ]);
        }, (new SpanContext)
            ->setOp('queue.publish')
            ->setDescription(self::STREAM)
            ->setData([
                'messaging.message.id' => (string) $job->id,
                'messaging.destination.name' => self::STREAM,
                'messaging.message.body.size' => strlen((string) $job->id),
            ]));
    }

    /**
     * Of the video's currently configured analysis types, only the ones whose
     * most recent job hasn't already completed - re-queuing a type that's
     * already done would just repeat the same Rekognition call for no new
     * result. A newly added type with no job yet, or a previously failed one,
     * is included.
     *
     * @param  array<int, string>  $analysisTypes
     * @return array<int, string>
     */
    private function typesNeedingAnalysis(Video $video, array $analysisTypes): array
    {
        $latestJobIds = $video->analysisJobs()
            ->selectRaw('MAX(id) as id')
            ->groupBy('type')
            ->pluck('id');

        $completedTypes = AnalysisJob::whereIn('id', $latestJobIds)
            ->where('status', 'completed')
            ->pluck('type')
            ->map(fn (AnalysisType $type) => $type->value)
            ->all();

        return array_values(array_diff($analysisTypes, $completedTypes));
    }
}
