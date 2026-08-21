<?php

namespace App\Actions\Video;

use App\Actions\Video\Concerns\BuildsAnalysisPromptData;
use App\Actions\Workspace\Concerns\AuthorizesWorkspaceAccess;
use App\Ai\Agents\VideoInquiryAgent;
use App\Models\User;
use App\Models\Video;
use App\Models\VideoInquiry;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Laravel\Ai\Exceptions\ProviderOverloadedException;
use Laravel\Ai\Exceptions\RateLimitedException;
use Throwable;

class InquireAboutVideo
{
    use AuthorizesWorkspaceAccess;
    use BuildsAnalysisPromptData;

    /**
     * Ask a free-text question about a video, grounded in its detection data
     * and any previously generated AI insights. Requires workspace
     * membership and at least one completed analysis job.
     *
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     */
    public function __invoke(User $user, Video $video, array $input): VideoInquiry
    {
        $this->authorizeMembership($user, $video->workspace);

        $validated = Validator::make($input, [
            'question' => ['required', 'string', 'min:3', 'max:500'],
        ])->validate();

        $video->loadMissing('analysisJobs.results', 'latestInsight');

        $jobs = $this->latestCompletedJobsByType($video);

        abort_if($jobs->isEmpty(), 422, 'This video has no completed analysis to investigate yet.');

        $data = [
            'question' => $validated['question'],
            ...$this->buildAnalysisPromptData($video, $jobs),
            'insights' => [
                'object_detection' => $video->latestInsight?->object_detection,
                'threat_assessment' => $video->latestInsight?->threat_assessment,
                'moderation' => $video->latestInsight?->moderation,
                'text_detection' => $video->latestInsight?->text_detection,
            ],
        ];

        return VideoInquiry::create([
            'video_id' => $video->id,
            'user_id' => $user->id,
            'question' => $validated['question'],
            'answer' => $this->promptAgent($data),
        ]);
    }

    /**
     * Prompt the inquiry agent, retrying with backoff if the AI provider
     * rate limits the request or is temporarily overloaded - same policy as
     * GenerateVideoInsights.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function promptAgent(array $data): array
    {
        return retry(
            times: 3,
            callback: fn () => (new VideoInquiryAgent)->prompt(json_encode($data, JSON_PRETTY_PRINT))->toArray(),
            sleepMilliseconds: fn (int $attempt) => $attempt * 500,
            when: fn (Throwable $e) => $e instanceof RateLimitedException || $e instanceof ProviderOverloadedException,
        );
    }
}
