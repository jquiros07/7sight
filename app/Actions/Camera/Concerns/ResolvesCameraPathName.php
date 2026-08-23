<?php

namespace App\Actions\Camera\Concerns;

use App\Models\Camera;

trait ResolvesCameraPathName
{
    /**
     * The deterministic MediaMTX path name this camera's stream is registered under.
     */
    private function pathName(Camera $camera): string
    {
        return "camera-{$camera->id}";
    }
}
