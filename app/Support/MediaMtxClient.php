<?php

namespace App\Support;

use Illuminate\Support\Facades\Http;

/**
 * Thin wrapper around MediaMTX's HTTP control API, used to dynamically
 * register/update/remove the RTSP source each camera restreams as HLS.
 * See https://github.com/bluenviron/mediamtx#api.
 */
class MediaMtxClient
{
    public function addPath(string $name, string $sourceUrl): void
    {
        Http::baseUrl(config('services.mediamtx.api_url'))
            ->post("/v3/config/paths/add/{$name}", ['source' => $sourceUrl])
            ->throw();
    }

    public function updatePath(string $name, string $sourceUrl): void
    {
        Http::baseUrl(config('services.mediamtx.api_url'))
            ->patch("/v3/config/paths/patch/{$name}", ['source' => $sourceUrl])
            ->throw();
    }

    public function removePath(string $name): void
    {
        Http::baseUrl(config('services.mediamtx.api_url'))
            ->delete("/v3/config/paths/delete/{$name}")
            ->throw();
    }

    /**
     * @return array<string, bool> path name => whether its source is currently connected/ready
     */
    public function listActivePaths(): array
    {
        $items = Http::baseUrl(config('services.mediamtx.api_url'))
            ->get('/v3/paths/list')
            ->throw()
            ->json('items') ?? [];

        return collect($items)->pluck('ready', 'name')->all();
    }

    /**
     * Path names currently configured in MediaMTX - regardless of whether
     * their source is actually reachable right now. Used to tell an
     * already-registered path (needs updatePath) apart from one MediaMTX
     * has no record of at all (needs addPath), e.g. after MediaMTX restarts
     * and loses its in-memory, non-persisted path registrations.
     *
     * @return list<string>
     */
    public function configuredPathNames(): array
    {
        $items = Http::baseUrl(config('services.mediamtx.api_url'))
            ->get('/v3/config/paths/list')
            ->throw()
            ->json('items') ?? [];

        return collect($items)->pluck('name')->all();
    }
}
