<?php

namespace App\Actions\Video;

use App\Actions\Video\Concerns\ValidatesAnalysisConfig;
use App\Actions\Workspace\Concerns\AuthorizesWorkspaceAccess;
use App\Enums\VideoStatus;
use App\Models\User;
use App\Models\Video;
use App\Models\Workspace;
use getID3;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UploadVideo
{
    use AuthorizesWorkspaceAccess;
    use ValidatesAnalysisConfig;

    private const MAX_DURATION_SECONDS = 15 * 60;

    private const MAX_LONG_EDGE = 1920;

    private const MAX_SHORT_EDGE = 1080;

    private const DISK = 'local';

    public function __construct(
        private readonly AnalyzeVideo $analyzeVideo,
    ) {}

    /**
     * Validate and store an uploaded video. Requires workspace membership.
     *
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     */
    public function __invoke(User $user, array $input): Video
    {
        $validator = Validator::make($input, array_merge([
            'workspace_id' => ['required', 'integer', Rule::exists('workspaces', 'id')->whereNull('deleted_at')],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'file' => ['required', 'file', 'mimes:mp4,mov,avi,mkv,webm', 'max:512000'],
        ], $this->analysisConfigRules()));

        $this->applyAnalysisConfigSometimes($validator);

        $validated = $validator->validate();

        $workspace = Workspace::findOrFail($validated['workspace_id']);
        $this->authorizeMembership($user, $workspace);

        /** @var UploadedFile $file */
        $file = $validated['file'];
        $path = $file->store("videos/{$workspace->id}/{$user->id}", self::DISK);

        $metadata = $this->inspect(Storage::disk(self::DISK)->path($path));

        if ($errors = $this->validationErrors($metadata)) {
            Storage::disk(self::DISK)->delete($path);

            throw ValidationException::withMessages(['file' => $errors]);
        }

        $video = Video::create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'status' => VideoStatus::Uploaded,
            'disk' => self::DISK,
            'path' => $path,
            'original_filename' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
            'duration_seconds' => (int) round($metadata['duration']),
            'width' => $metadata['width'],
            'height' => $metadata['height'],
            'thumbnail_path' => null,
            'analysis_types' => $validated['analysis_types'] ?? [],
            'auto_start_analysis' => $validated['auto_start_analysis'] ?? false,
            'analysis_config' => $validated['analysis_config'] ?? [],
        ]);

        if ($video->auto_start_analysis) {
            $video = ($this->analyzeVideo)($user, $video);
        }

        return $video;
    }

    /**
     * @return array{duration: float|null, width: int|null, height: int|null}
     */
    private function inspect(string $absolutePath): array
    {
        $getID3 = new getID3;
        $info = $getID3->analyze($absolutePath);

        return [
            'duration' => $info['playtime_seconds'] ?? null,
            'width' => $info['video']['resolution_x'] ?? null,
            'height' => $info['video']['resolution_y'] ?? null,
        ];
    }

    /**
     * @param  array{duration: float|null, width: int|null, height: int|null}  $metadata
     * @return list<string>
     */
    private function validationErrors(array $metadata): array
    {
        if ($metadata['duration'] === null || $metadata['width'] === null || $metadata['height'] === null) {
            return ['That file could not be read as a video. Please choose a different file.'];
        }

        $errors = [];

        if ($metadata['duration'] > self::MAX_DURATION_SECONDS) {
            $errors[] = 'Videos must be 15 minutes or shorter.';
        }

        $longEdge = max($metadata['width'], $metadata['height']);
        $shortEdge = min($metadata['width'], $metadata['height']);

        if ($longEdge > self::MAX_LONG_EDGE || $shortEdge > self::MAX_SHORT_EDGE) {
            $errors[] = 'Videos must be 1080p or lower.';
        }

        return $errors;
    }
}
