import { useEffect, useRef, useState } from 'react';
import { formatDuration } from '../lib/format';

export function TrimScrubber({
    durationSeconds,
    start,
    end,
    onChange,
}: {
    durationSeconds: number;
    start: number;
    end: number;
    onChange: (start: number, end: number) => void;
}) {
    const trackRef = useRef<HTMLDivElement>(null);
    const [dragging, setDragging] = useState<'start' | 'end' | null>(null);

    useEffect(() => {
        if (!dragging) return;

        function handleMove(e: PointerEvent) {
            const track = trackRef.current;
            if (!track || durationSeconds <= 0) return;
            const rect = track.getBoundingClientRect();
            const ratio = Math.min(1, Math.max(0, (e.clientX - rect.left) / rect.width));
            const time = ratio * durationSeconds;

            if (dragging === 'start') {
                onChange(Math.min(time, end - 1), end);
            } else {
                onChange(start, Math.max(time, start + 1));
            }
        }

        function handleUp() {
            setDragging(null);
        }

        window.addEventListener('pointermove', handleMove);
        window.addEventListener('pointerup', handleUp);
        return () => {
            window.removeEventListener('pointermove', handleMove);
            window.removeEventListener('pointerup', handleUp);
        };
    }, [dragging, start, end, durationSeconds, onChange]);

    const startPct = durationSeconds > 0 ? (Math.min(start, durationSeconds) / durationSeconds) * 100 : 0;
    const endPct = durationSeconds > 0 ? (Math.min(end, durationSeconds) / durationSeconds) * 100 : 100;

    return (
        <div className="flex flex-col gap-2">
            <div ref={trackRef} className="relative h-2 rounded-full bg-layer-line">
                <div
                    className="absolute h-2 rounded-full bg-primary"
                    style={{ left: `${startPct}%`, width: `${Math.max(0, endPct - startPct)}%` }}
                />
                <button
                    type="button"
                    aria-label="Trim start"
                    onPointerDown={() => setDragging('start')}
                    className="absolute top-1/2 size-4 -translate-x-1/2 -translate-y-1/2 touch-none rounded-full border-2 border-primary bg-white shadow"
                    style={{ left: `${startPct}%` }}
                />
                <button
                    type="button"
                    aria-label="Trim end"
                    onPointerDown={() => setDragging('end')}
                    className="absolute top-1/2 size-4 -translate-x-1/2 -translate-y-1/2 touch-none rounded-full border-2 border-primary bg-white shadow"
                    style={{ left: `${endPct}%` }}
                />
            </div>
            <p className="text-xs text-muted-foreground-1">
                {formatDuration(start)} – {formatDuration(end)} ({formatDuration(Math.max(0, end - start))} clip)
            </p>
        </div>
    );
}
