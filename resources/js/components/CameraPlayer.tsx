import type HlsType from 'hls.js';
import { VideoOff } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

export function CameraPlayer({ hlsUrl, isLive, name }: { hlsUrl: string; isLive: boolean; name: string }) {
    const videoRef = useRef<HTMLVideoElement>(null);
    const [errored, setErrored] = useState(false);

    useEffect(() => {
        const video = videoRef.current;
        if (!video || !isLive) return;

        setErrored(false);

        // Safari plays HLS natively without hls.js - skip loading the library entirely.
        if (video.canPlayType('application/vnd.apple.mpegurl')) {
            video.src = hlsUrl;
            return;
        }

        let cancelled = false;
        let hls: HlsType | undefined;

        import('hls.js').then(({ default: Hls }) => {
            if (cancelled || !video) return;

            if (Hls.isSupported()) {
                hls = new Hls();
                hls.on(Hls.Events.ERROR, (_event, data) => {
                    if (data.fatal) setErrored(true);
                });
                hls.loadSource(hlsUrl);
                hls.attachMedia(video);
            } else {
                setErrored(true);
            }
        });

        return () => {
            cancelled = true;
            hls?.destroy();
        };
    }, [hlsUrl, isLive]);

    const offline = !isLive || errored;

    return (
        <div className="relative aspect-video overflow-hidden rounded-lg bg-black">
            {offline ? (
                <div className="flex h-full flex-col items-center justify-center gap-2 text-muted-foreground-2">
                    <VideoOff className="size-8" strokeWidth={1.5} />
                    <span className="text-xs">{name} is offline</span>
                </div>
            ) : (
                <video ref={videoRef} autoPlay muted playsInline className="h-full w-full object-contain" />
            )}
        </div>
    );
}
