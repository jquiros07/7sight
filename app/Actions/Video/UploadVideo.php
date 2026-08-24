<?php

namespace App\Actions\Video;

use App\Actions\Video\Concerns\ValidatesAnalysisConfig;
use App\Actions\Video\Concerns\ValidatesVideoMetadata;
use App\Actions\Workspace\Concerns\AuthorizesWorkspaceAccess;
use App\Enums\VideoStatus;
use App\Models\User;
use App\Models\Video;
use App\Models\Workspace;
use App\Support\VideoMetadataInspector;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UploadVideo
{
    use AuthorizesWorkspaceAccess;
    use ValidatesAnalysisConfig;
    use ValidatesVideoMetadata;

    private const DISK = 'local';

    public function __construct(
        private readonly AnalyzeVideo $analyzeVideo,
        private readonly VideoMetadataInspector $inspector,
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
            'file' => ['required', 'file', 'mimes:mp4,mov,avi,mkv,webm', 'max:'.(self::MAX_FILE_SIZE_BYTES / 1024)],
        ], $this->analysisConfigRules()));

        $this->applyAnalysisConfigSometimes($validator);

        $validated = $validator->validate();

        $workspace = Workspace::findOrFail($validated['workspace_id']);
        $this->authorizeMembership($user, $workspace);

        /** @var UploadedFile $file */
        $file = $validated['file'];
        $path = $file->store("videos/{$workspace->id}/{$user->id}", self::DISK);

        $metadata = $this->inspector->inspect(Storage::disk(self::DISK)->path($path));

        if ($errors = $this->metadataValidationErrors($metadata)) {
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
}
