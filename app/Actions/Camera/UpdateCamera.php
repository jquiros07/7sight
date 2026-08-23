<?php

namespace App\Actions\Camera;

use App\Actions\Camera\Concerns\ResolvesCameraPathName;
use App\Models\Camera;
use App\Support\MediaMtxClient;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Throwable;

class UpdateCamera
{
    use ResolvesCameraPathName;

    public function __construct(
        private readonly MediaMtxClient $mediaMtx,
    ) {}

    /**
     * Update a camera's details. Re-registers its MediaMTX source if the stream URL changed.
     *
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     */
    public function __invoke(Camera $camera, array $input): Camera
    {
        $validated = Validator::make($input, [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
            'stream_url' => ['sometimes', 'required', 'string', 'regex:/^rtsp:\/\//i'],
        ])->validate();

        $streamUrlChanged = isset($validated['stream_url']) && $validated['stream_url'] !== $camera->stream_url;

        try {
            return DB::transaction(function () use ($camera, $validated, $streamUrlChanged) {
                $camera->update($validated);

                if ($streamUrlChanged) {
                    $this->mediaMtx->updatePath($this->pathName($camera), $validated['stream_url']);
                }

                return $camera;
            });
        } catch (Throwable $e) {
            Log::error('Failed to update camera on MediaMTX', ['exception' => $e]);

            abort(503, 'Could not connect to the camera stream. Please check the stream URL and try again.');
        }
    }
}
