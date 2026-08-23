<?php

namespace App\Actions\Camera;

use App\Actions\Camera\Concerns\ResolvesCameraPathName;
use App\Models\Camera;
use App\Models\User;
use App\Support\MediaMtxClient;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Throwable;

class CreateCamera
{
    use ResolvesCameraPathName;

    private const MAX_CAMERAS = 5;

    public function __construct(
        private readonly MediaMtxClient $mediaMtx,
    ) {}

    /**
     * Validate and register a new IP camera, then start restreaming it via MediaMTX.
     *
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     */
    public function __invoke(User $user, array $input): Camera
    {
        $validated = Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
            'stream_url' => ['required', 'string', 'regex:/^rtsp:\/\//i'],
        ])->validate();

        abort_if(Camera::count() >= self::MAX_CAMERAS, 422, 'You can register at most '.self::MAX_CAMERAS.' cameras.');

        try {
            return DB::transaction(function () use ($user, $validated) {
                $camera = Camera::create([
                    'name' => $validated['name'],
                    'location' => $validated['location'] ?? null,
                    'stream_url' => $validated['stream_url'],
                    'created_by' => $user->id,
                ]);

                $this->mediaMtx->addPath($this->pathName($camera), $validated['stream_url']);

                return $camera;
            });
        } catch (Throwable $e) {
            Log::error('Failed to register camera with MediaMTX', ['exception' => $e]);

            abort(503, 'Could not connect to the camera stream. Please check the stream URL and try again.');
        }
    }
}
