<?php

namespace App\Actions\Video;

use App\Actions\Video\Concerns\AuthorizesVideoAccess;
use App\Actions\Video\Concerns\ValidatesAnalysisConfig;
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
        $this->authorizeVideoManagement($user, $video);

        $validator = Validator::make($input, array_merge([
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ], $this->analysisConfigRules(required: false)));

        $this->applyAnalysisConfigSometimes($validator);

        $video->update($validator->validate());

        return $video;
    }
}
