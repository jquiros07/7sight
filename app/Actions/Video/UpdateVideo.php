<?php

namespace App\Actions\Video;

use App\Actions\Video\Concerns\AuthorizesVideoAccess;
use App\Actions\Video\Concerns\ValidatesAnalysisConfig;
use App\Enums\VideoStatus;
use App\Models\User;
use App\Models\Video;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class UpdateVideo
{
    use AuthorizesVideoAccess;
    use ValidatesAnalysisConfig;

    /**
     * Update a video's details and analysis settings. Requires being the uploader or a workspace owner/admin.
     *
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     */
    public function __invoke(User $user, Video $video, array $input): Video
    {
        $this->authorizeVideoManagement($user, $video, 'videos.update');

        $validator = Validator::make($input, array_merge([
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ], $this->analysisConfigRules(required: false)));

        $this->applyAnalysisConfigSometimes($validator);

        $validated = $validator->validate();

        // A changed type list makes the previously computed status stale (e.g. a
        // video already marked "ready" would otherwise hide the newly added type
        // from the analyze action). Skip the reset while analysis is actively
        // running so this doesn't mask an in-progress job.
        if (
            array_key_exists('analysis_types', $validated)
            && $video->status !== VideoStatus::Processing
            && $this->typesChanged($validated['analysis_types'], $video->analysis_types)
        ) {
            $validated['status'] = VideoStatus::Uploaded;
        }

        $video->update($validated);

        return $video;
    }

    /**
     * @param  array<int, string>  $newTypes
     * @param  array<int, string>|null  $currentTypes
     */
    private function typesChanged(array $newTypes, ?array $currentTypes): bool
    {
        $current = $currentTypes ?? [];

        sort($newTypes);
        sort($current);

        return $newTypes !== $current;
    }
}
