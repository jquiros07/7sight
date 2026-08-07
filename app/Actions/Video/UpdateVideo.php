<?php

namespace App\Actions\Video;

use App\Actions\Video\Concerns\AuthorizesVideoAccess;
use App\Models\User;
use App\Models\Video;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class UpdateVideo
{
    use AuthorizesVideoAccess;

    /**
     * Update a video's title/description. Requires being the uploader or a workspace owner/admin.
     *
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     */
    public function __invoke(User $user, Video $video, array $input): Video
    {
        $this->authorizeVideoManagement($user, $video);

        $validated = Validator::make($input, [
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ])->validate();

        $video->update($validated);

        return $video;
    }
}
