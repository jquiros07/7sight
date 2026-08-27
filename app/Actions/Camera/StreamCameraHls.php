<?php

namespace App\Actions\Camera;

use App\Actions\Camera\Concerns\AuthorizesCameraAccess;
use App\Actions\Camera\Concerns\ResolvesCameraPathName;
use App\Models\Camera;
use App\Models\User;
use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class StreamCameraHls
{
    use AuthorizesCameraAccess;
    use ResolvesCameraPathName;

    /**
     * Proxies a camera's live HLS playlist/segment files from MediaMTX.
     * Requires workspace membership - this is what keeps live camera feeds
     * from being reachable by anyone who knows or guesses the deterministic
     * camera-{id} MediaMTX path name, since MediaMTX itself has no auth.
     */
    public function __invoke(User $user, Camera $camera, string $path): Response
    {
        $this->authorizeCameraAccess($user, $camera);

        $url = rtrim(config('services.mediamtx.internal_hls_url'), '/')."/{$this->pathName($camera)}/{$path}";

        try {
            // MediaMTX's HLS server redirects the initial manifest request
            // through a cookie-based handshake (302 to the same URL with
            // ?cookieCheck=1, setting a cookie, before it'll serve the real
            // playlist). Guzzle follows redirects by default but doesn't
            // carry cookies across them unless given a cookie jar - without
            // one, this call would silently return MediaMTX's redirect
            // response instead of the manifest.
            $upstream = Http::timeout(10)->withOptions(['cookies' => new CookieJar])->get($url);
        } catch (Throwable $e) {
            Log::error('Failed to reach MediaMTX for HLS proxy', ['exception' => $e]);

            abort(502, 'Could not reach the camera stream.');
        }

        // MediaMTX embeds a one-time session id in the manifest it issues on
        // each fetch, and invalidates the previous one when a new manifest is
        // requested. If the browser (or an OS media stack, for native <video>
        // playback) ever caches this response, it'll keep replaying a stale
        // session id that MediaMTX has already superseded, and every
        // subsequent segment/sub-playlist request 401s. This must never be
        // cached.
        return response($upstream->body(), $upstream->status())
            ->header('Content-Type', $upstream->header('Content-Type') ?: 'application/octet-stream')
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
    }
}
