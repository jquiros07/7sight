<?php

namespace App\Console\Commands;

use App\Actions\Camera\Concerns\ResolvesCameraPathName;
use App\Models\Camera;
use App\Support\MediaMtxClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class SyncCamerasWithMediaMtx extends Command
{
    use ResolvesCameraPathName;

    protected $signature = 'cameras:sync';

    protected $description = "Re-register every camera's path with MediaMTX. MediaMTX doesn't persist paths added via its API across restarts, so this re-syncs it with the database (the source of truth) - run on every app boot so a MediaMTX restart doesn't strand existing cameras.";

    public function handle(MediaMtxClient $mediaMtx): int
    {
        try {
            $configuredPaths = $mediaMtx->configuredPathNames();
            $cameras = Camera::all();
        } catch (Throwable $e) {
            Log::error('Camera sync: could not read camera state, skipping.', ['exception' => $e]);

            return self::SUCCESS;
        }

        foreach ($cameras as $camera) {
            try {
                $path = $this->pathName($camera);

                if (in_array($path, $configuredPaths, true)) {
                    $mediaMtx->updatePath($path, $camera->stream_url);
                } else {
                    $mediaMtx->addPath($path, $camera->stream_url);
                }
            } catch (Throwable $e) {
                Log::error("Camera sync: failed to sync camera {$camera->id} with MediaMTX.", ['exception' => $e]);
            }
        }

        return self::SUCCESS;
    }
}
