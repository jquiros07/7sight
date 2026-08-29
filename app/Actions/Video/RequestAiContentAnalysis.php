<?php

namespace App\Actions\Video;

use App\Actions\Workspace\Concerns\AuthorizesWorkspaceAccess;
use App\Jobs\AnalyzeAiGeneratedContentJob;
use App\Models\AiContentAnalysis;
use App\Models\User;
use App\Models\Video;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class RequestAiContentAnalysis
{
    use AuthorizesWorkspaceAccess;

    private const MAX_CLIP_SECONDS = 90;

    /**
     * Queue an AI-generated-content check against a clip of the video.
     * Requires 'videos.detect-ai-content' in the video's workspace - a
     * cost-driving AI call, so (unlike the ffmpeg-only Video Tools) there's
     * no uploader ownership bypass.
     *
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     */
    public function __invoke(User $user, Video $video, array $input): AiContentAnalysis
    {
        $this->authorizePermission($user, $video->workspace, 'videos.detect-ai-content');

        $validated = Validator::make($input, [
            'start_seconds' => ['required', 'numeric', 'min:0', 'lt:end_seconds'],
            'end_seconds' => ['required', 'numeric', 'max:'.$video->duration_seconds],
        ], [], ['start_seconds' => 'start time', 'end_seconds' => 'end time'])
            ->after(function ($validator) use ($input) {
                if (($input['end_seconds'] ?? 0) - ($input['start_seconds'] ?? 0) > self::MAX_CLIP_SECONDS) {
                    $validator->errors()->add('end_seconds', 'The selected clip cannot be longer than 90 seconds.');
                }
            })
            ->validate();

        $analysis = AiContentAnalysis::create([
            'video_id' => $video->id,
            'user_id' => $user->id,
            'status' => 'pending',
            'start_seconds' => $validated['start_seconds'],
            'end_seconds' => $validated['end_seconds'],
        ]);

        AnalyzeAiGeneratedContentJob::dispatch($analysis);

        return $analysis;
    }
}
