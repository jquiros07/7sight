<?php

namespace App\Actions\Camera;

use App\Actions\Camera\Concerns\AuthorizesCameraAccess;
use App\Actions\Video\AnalyzeVideo;
use App\Actions\Video\Concerns\ValidatesAnalysisConfig;
use App\Actions\Video\Concerns\ValidatesVideoMetadata;
use App\Enums\CameraRecordingStatus;
use App\Enums\VideoStatus;
use App\Models\Camera;
use App\Models\CameraRecording;
use App\Models\User;
use App\Models\Video;
use App\Support\VideoMetadataInspector;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CreateVideoFromRecordingClip
{
    use AuthorizesCameraAccess;
    use ValidatesAnalysisConfig;
    use ValidatesVideoMetadata;

    private const MAX_CLIP_SECONDS = 15 * 60;

    private const DISK = 'local';

    public function __construct(
        private readonly AnalyzeVideo $analyzeVideo,
        private readonly VideoMetadataInspector $inspector,
    ) {}

    /**
     * Cut a timestamp range out of a completed recording and create a Video
     * from it in the camera's workspace, optionally kicking off analysis.
     * Requires workspace membership.
     *
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     */
    public function __invoke(User $user, Camera $camera, CameraRecording $recording, array $input): Video
    {
        $this->authorizeCameraAccess($user, $camera);

        abort_if($recording->camera_id !== $camera->id, 404);
        abort_if($recording->status !== CameraRecordingStatus::Completed, 422, 'This recording is not ready to clip from yet.');

        $validator = Validator::make($input, array_merge([
            'start_seconds' => ['required', 'integer', 'min:0'],
            'end_seconds' => ['required', 'integer', 'gt:start_seconds', 'max:'.$recording->duration_seconds],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ], $this->analysisConfigRules()));

        $this->applyAnalysisConfigSometimes($validator);

        $validated = $validator->validate();

        $clipSeconds = $validated['end_seconds'] - $validated['start_seconds'];

        if ($clipSeconds > self::MAX_CLIP_SECONDS) {
            throw ValidationException::withMessages(['end_seconds' => 'Clips must be 15 minutes or shorter.']);
        }

        $sourcePath = Storage::disk($recording->disk)->path($recording->path);
        $clipPath = "videos/{$camera->workspace_id}/{$user->id}/".Str::uuid().'.mp4';
        $absoluteClipPath = Storage::disk(self::DISK)->path($clipPath);

        Storage::disk(self::DISK)->makeDirectory(dirname($clipPath));

        $result = Process::timeout(120)->run([
            'ffmpeg', '-y',
            '-ss', (string) $validated['start_seconds'],
            '-to', (string) $validated['end_seconds'],
            '-i', $sourcePath,
            '-c', 'copy',
            $absoluteClipPath,
        ]);

        if ($result->failed()) {
            Log::error('Failed to cut clip from camera recording', ['recording_id' => $recording->id, 'error_output' => $result->errorOutput()]);
            Storage::disk(self::DISK)->delete($clipPath);

            abort(500, 'Could not create the clip. Please try again.');
        }

        $metadata = $this->inspector->inspect($absoluteClipPath);
        $sizeBytes = Storage::disk(self::DISK)->size($clipPath);

        if ($errors = $this->metadataValidationErrors($metadata, $sizeBytes)) {
            Storage::disk(self::DISK)->delete($clipPath);

            throw ValidationException::withMessages(['end_seconds' => $errors]);
        }

        $video = Video::create([
            'workspace_id' => $camera->workspace_id,
            'user_id' => $user->id,
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'status' => VideoStatus::Uploaded,
            'disk' => self::DISK,
            'path' => $clipPath,
            'original_filename' => basename($clipPath),
            'mime_type' => 'video/mp4',
            'size' => $sizeBytes,
            'duration_seconds' => (int) round($metadata['duration']),
            'width' => $metadata['width'],
            'height' => $metadata['height'],
            'thumbnail_path' => null,
            'camera_recording_id' => $recording->id,
            'clip_start_seconds' => $validated['start_seconds'],
            'clip_end_seconds' => $validated['end_seconds'],
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
