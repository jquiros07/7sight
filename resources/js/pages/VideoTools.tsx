import { FormEvent, useEffect, useRef, useState } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import { AppLayout } from '@/components/AppLayout';
import { ChevronLeft, Download, ImageIcon, Loader2, Music, Scissors, Wrench } from 'lucide-react';
import { api } from '../lib/api';
import { useAuth } from '../lib/auth';
import { getErrorMessages } from '../lib/errors';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { TrimScrubber } from '@/components/TrimScrubber';
import { Button, buttonVariants } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type VideoToolsSubject = {
    id: number;
    title: string;
    workspace_id: number;
    duration_seconds: number | null;
};

type VideoToolJob = {
    id: number;
    type: 'trim' | 'resize';
    status: 'pending' | 'processing' | 'completed' | 'failed';
    error_message: string | null;
};

const MAX_WIDTH = 3840;
const MAX_HEIGHT = 2160;

function useToolJobPolling(videoId: number, job: VideoToolJob | null, onUpdate: (job: VideoToolJob) => void) {
    useEffect(() => {
        if (!job || job.status === 'completed' || job.status === 'failed') return;

        const interval = setInterval(async () => {
            try {
                const res = await api.get<VideoToolJob>(`/api/videos/${videoId}/tool-jobs/${job.id}`);
                onUpdate(res.data);
            } catch {
                // Transient network hiccup - just skip this tick and retry on the next one.
            }
        }, 3000);

        return () => clearInterval(interval);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [videoId, job?.id, job?.status]);
}

function downloadBlob(blob: Blob, fileName: string) {
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = fileName;
    document.body.appendChild(link);
    link.click();
    link.remove();
    URL.revokeObjectURL(url);
}

function ToolJobStatus({ job, videoId }: { job: VideoToolJob; videoId: number }) {
    if (job.status === 'pending' || job.status === 'processing') {
        return (
            <p className="flex items-center gap-2 text-sm text-muted-foreground-1">
                <Loader2 className="size-4 animate-spin" strokeWidth={1.75} />
                {job.status === 'pending' ? 'Queued…' : 'Processing…'}
            </p>
        );
    }

    if (job.status === 'failed') {
        return (
            <Alert variant="destructive">
                <AlertDescription>{job.error_message ?? 'The job failed.'}</AlertDescription>
            </Alert>
        );
    }

    return (
        <Button variant="primary" onClick={() => window.open(`/api/videos/${videoId}/tool-jobs/${job.id}/download`, '_blank')}>
            <Download className="size-4" strokeWidth={1.75} />
            Download
        </Button>
    );
}

export default function VideoTools() {
    const { id } = useParams<{ id: string }>();
    const navigate = useNavigate();
    const { can } = useAuth();

    const [video, setVideo] = useState<VideoToolsSubject | null>(null);
    const videoRef = useRef<HTMLVideoElement>(null);
    const [loading, setLoading] = useState(true);
    const [loadError, setLoadError] = useState<string[]>([]);

    const [thumbnailGenerating, setThumbnailGenerating] = useState(false);
    const [thumbnailError, setThumbnailError] = useState<string[]>([]);
    const [thumbnailUrl, setThumbnailUrl] = useState<string | null>(null);

    const [audioExtracting, setAudioExtracting] = useState(false);
    const [audioError, setAudioError] = useState<string[]>([]);

    const [trimStart, setTrimStart] = useState('');
    const [trimEnd, setTrimEnd] = useState('');
    const [trimSubmitting, setTrimSubmitting] = useState(false);
    const [trimError, setTrimError] = useState<string[]>([]);
    const [trimJob, setTrimJob] = useState<VideoToolJob | null>(null);

    const [resizeWidth, setResizeWidth] = useState('');
    const [resizeHeight, setResizeHeight] = useState('');
    const [resizeSubmitting, setResizeSubmitting] = useState(false);
    const [resizeError, setResizeError] = useState<string[]>([]);
    const [resizeJob, setResizeJob] = useState<VideoToolJob | null>(null);

    useEffect(() => {
        api.get<VideoToolsSubject>(`/api/videos/${id}`)
            .then((res) => setVideo(res.data))
            .catch((err) => setLoadError(getErrorMessages(err)))
            .finally(() => setLoading(false));
    }, [id]);

    useEffect(() => {
        if (video?.duration_seconds == null) return;
        setTrimStart((current) => (current === '' ? '0' : current));
        setTrimEnd((current) => (current === '' ? String(video.duration_seconds) : current));
    }, [video?.duration_seconds]);

    useToolJobPolling(video?.id ?? 0, trimJob, setTrimJob);
    useToolJobPolling(video?.id ?? 0, resizeJob, setResizeJob);

    async function handleGenerateThumbnail() {
        if (!video) return;
        setThumbnailGenerating(true);
        setThumbnailError([]);
        try {
            await api.post(`/api/videos/${video.id}/thumbnail`);
            setThumbnailUrl(`/api/videos/${video.id}/thumbnail?t=${Date.now()}`);
        } catch (err) {
            setThumbnailError(getErrorMessages(err));
        } finally {
            setThumbnailGenerating(false);
        }
    }

    async function handleExtractAudio() {
        if (!video) return;
        setAudioExtracting(true);
        setAudioError([]);
        try {
            const res = await api.post(`/api/videos/${video.id}/extract-audio`, null, { responseType: 'blob' });
            downloadBlob(res.data, `${video.title || 'video'}.mp3`);
        } catch (err) {
            setAudioError(getErrorMessages(err));
        } finally {
            setAudioExtracting(false);
        }
    }

    async function handleSubmitTrim(e: FormEvent) {
        e.preventDefault();
        if (!video) return;
        setTrimSubmitting(true);
        setTrimError([]);
        try {
            const res = await api.post<VideoToolJob>(`/api/videos/${video.id}/trim`, {
                start_seconds: Number(trimStart),
                end_seconds: Number(trimEnd),
            });
            setTrimJob(res.data);
        } catch (err) {
            setTrimError(getErrorMessages(err));
        } finally {
            setTrimSubmitting(false);
        }
    }

    async function handleSubmitResize(e: FormEvent) {
        e.preventDefault();
        if (!video) return;
        setResizeSubmitting(true);
        setResizeError([]);
        try {
            const res = await api.post<VideoToolJob>(`/api/videos/${video.id}/resize`, {
                width: Number(resizeWidth),
                height: Number(resizeHeight),
            });
            setResizeJob(res.data);
        } catch (err) {
            setResizeError(getErrorMessages(err));
        } finally {
            setResizeSubmitting(false);
        }
    }

    const canGenerateThumbnail = video ? can(video.workspace_id, 'videos.generate-thumbnail') : false;
    const canExtractAudio = video ? can(video.workspace_id, 'videos.extract-audio') : false;
    const canTrim = video ? can(video.workspace_id, 'videos.trim') : false;
    const canResize = video ? can(video.workspace_id, 'videos.resize') : false;

    return (
        <AppLayout active="videos">
            <Button variant="secondary" onClick={() => navigate('/videos')}>
                <ChevronLeft className="size-4" strokeWidth={1.75} />
                Back to videos
            </Button>

            {loadError.length > 0 && (
                <Alert variant="destructive" className="mt-4" onDismiss={() => setLoadError([])}>
                    <AlertDescription>
                        <ul className="list-disc space-y-1 pl-4">
                            {loadError.map((message) => (
                                <li key={message}>{message}</li>
                            ))}
                        </ul>
                    </AlertDescription>
                </Alert>
            )}

            {loading && (
                <div className="mt-10 flex flex-col items-center gap-2 text-center">
                    <Loader2 className="size-6 animate-spin text-primary" strokeWidth={1.75} />
                    <p className="text-sm text-muted-foreground-1">Loading…</p>
                </div>
            )}

            {!loading && video && (
                <>
                    <h1 className="mt-4 font-heading text-2xl font-medium">{video.title}</h1>

                    <video
                        ref={videoRef}
                        controls
                        className="mt-4 aspect-video w-full rounded-xl bg-black shadow-lg"
                        src={`/api/videos/${video.id}/stream`}
                    />

                    <div className="mt-6 flex items-center gap-2 border-b border-layer-line pb-3">
                        <Wrench className="size-5 text-primary" strokeWidth={1.75} />
                        <div>
                            <h2 className="font-heading text-xl font-medium text-foreground">Tools</h2>
                            <p className="text-sm text-muted-foreground-1">Generate a thumbnail, extract audio, trim, or resize this video</p>
                        </div>
                    </div>

                    <div className="mt-4 grid grid-cols-1 gap-6 lg:grid-cols-2">
                        {canGenerateThumbnail && (
                            <Card>
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2 text-base">
                                        <ImageIcon className="size-4" strokeWidth={1.75} />
                                        Thumbnail
                                    </CardTitle>
                                </CardHeader>
                                <CardContent className="flex flex-col gap-3">
                                    {thumbnailError.length > 0 && (
                                        <Alert variant="destructive" onDismiss={() => setThumbnailError([])}>
                                            <AlertDescription>{thumbnailError.join(' ')}</AlertDescription>
                                        </Alert>
                                    )}
                                    {thumbnailUrl && (
                                        <img
                                            src={thumbnailUrl}
                                            alt="Generated thumbnail"
                                            className="max-w-xs rounded-lg border border-card-line"
                                        />
                                    )}
                                    <div className="flex items-center gap-2">
                                        <Button variant="secondary" onClick={handleGenerateThumbnail} disabled={thumbnailGenerating}>
                                            {thumbnailGenerating && <Loader2 className="size-4 animate-spin" strokeWidth={1.75} />}
                                            {thumbnailGenerating ? 'Generating…' : 'Generate thumbnail'}
                                        </Button>
                                        {thumbnailUrl && (
                                            <a
                                                href={thumbnailUrl}
                                                download={`${video.title || 'video'}-thumbnail.jpg`}
                                                className={buttonVariants('secondary')}
                                            >
                                                <Download className="size-4" strokeWidth={1.75} />
                                                Download
                                            </a>
                                        )}
                                    </div>
                                </CardContent>
                            </Card>
                        )}

                        {canExtractAudio && (
                            <Card>
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2 text-base">
                                        <Music className="size-4" strokeWidth={1.75} />
                                        Extract audio
                                    </CardTitle>
                                </CardHeader>
                                <CardContent className="flex flex-col gap-3">
                                    {audioError.length > 0 && (
                                        <Alert variant="destructive" onDismiss={() => setAudioError([])}>
                                            <AlertDescription>{audioError.join(' ')}</AlertDescription>
                                        </Alert>
                                    )}
                                    <Button variant="secondary" onClick={handleExtractAudio} disabled={audioExtracting}>
                                        {audioExtracting && <Loader2 className="size-4 animate-spin" strokeWidth={1.75} />}
                                        {audioExtracting ? 'Extracting…' : 'Extract audio'}
                                    </Button>
                                </CardContent>
                            </Card>
                        )}

                        {canTrim && (
                            <Card>
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2 text-base">
                                        <Scissors className="size-4" strokeWidth={1.75} />
                                        Trim
                                    </CardTitle>
                                </CardHeader>
                                <CardContent className="flex flex-col gap-3">
                                    {video.duration_seconds ? (
                                        <TrimScrubber
                                            durationSeconds={video.duration_seconds}
                                            start={Number(trimStart) || 0}
                                            end={Number(trimEnd) || video.duration_seconds}
                                            onChange={(start, end) => {
                                                setTrimStart(String(Math.round(start)));
                                                setTrimEnd(String(Math.round(end)));
                                            }}
                                        />
                                    ) : (
                                        <p className="text-xs text-muted-foreground-1">Duration unknown.</p>
                                    )}
                                    <form onSubmit={handleSubmitTrim} className="flex items-end gap-2">
                                        <div className="flex flex-col gap-1">
                                            <Label htmlFor="trim-start">Start (s)</Label>
                                            <Input
                                                id="trim-start"
                                                type="number"
                                                min={0}
                                                required
                                                className="w-28"
                                                value={trimStart}
                                                onChange={(e) => setTrimStart(e.target.value)}
                                            />
                                            <button
                                                type="button"
                                                onClick={() => setTrimStart(String(Math.floor(videoRef.current?.currentTime ?? 0)))}
                                                className="text-left text-xs text-primary hover:underline"
                                            >
                                                Use current time
                                            </button>
                                        </div>
                                        <div className="flex flex-col gap-1">
                                            <Label htmlFor="trim-end">End (s)</Label>
                                            <Input
                                                id="trim-end"
                                                type="number"
                                                min={0}
                                                required
                                                className="w-28"
                                                value={trimEnd}
                                                onChange={(e) => setTrimEnd(e.target.value)}
                                            />
                                            <button
                                                type="button"
                                                onClick={() => setTrimEnd(String(Math.floor(videoRef.current?.currentTime ?? 0)))}
                                                className="text-left text-xs text-primary hover:underline"
                                            >
                                                Use current time
                                            </button>
                                        </div>
                                        <Button
                                            type="submit"
                                            variant="secondary"
                                            disabled={trimSubmitting || trimJob?.status === 'pending' || trimJob?.status === 'processing'}
                                        >
                                            {trimSubmitting && <Loader2 className="size-4 animate-spin" strokeWidth={1.75} />}
                                            Trim
                                        </Button>
                                    </form>
                                    {trimError.length > 0 && (
                                        <Alert variant="destructive" onDismiss={() => setTrimError([])}>
                                            <AlertDescription>{trimError.join(' ')}</AlertDescription>
                                        </Alert>
                                    )}
                                    {trimJob && <ToolJobStatus job={trimJob} videoId={video.id} />}
                                </CardContent>
                            </Card>
                        )}

                        {canResize && (
                            <Card>
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2 text-base">
                                        <Scissors className="size-4 rotate-90" strokeWidth={1.75} />
                                        Resize / transcode
                                    </CardTitle>
                                </CardHeader>
                                <CardContent className="flex flex-col gap-3">
                                    <p className="text-xs text-muted-foreground-1">
                                        Up to {MAX_WIDTH}×{MAX_HEIGHT}.
                                    </p>
                                    <form onSubmit={handleSubmitResize} className="flex items-end gap-2">
                                        <div className="flex flex-col gap-1">
                                            <Label htmlFor="resize-width">Width</Label>
                                            <Input
                                                id="resize-width"
                                                type="number"
                                                min={1}
                                                max={MAX_WIDTH}
                                                required
                                                className="w-28"
                                                value={resizeWidth}
                                                onChange={(e) => setResizeWidth(e.target.value)}
                                            />
                                        </div>
                                        <div className="flex flex-col gap-1">
                                            <Label htmlFor="resize-height">Height</Label>
                                            <Input
                                                id="resize-height"
                                                type="number"
                                                min={1}
                                                max={MAX_HEIGHT}
                                                required
                                                className="w-28"
                                                value={resizeHeight}
                                                onChange={(e) => setResizeHeight(e.target.value)}
                                            />
                                        </div>
                                        <Button
                                            type="submit"
                                            variant="secondary"
                                            disabled={resizeSubmitting || resizeJob?.status === 'pending' || resizeJob?.status === 'processing'}
                                        >
                                            {resizeSubmitting && <Loader2 className="size-4 animate-spin" strokeWidth={1.75} />}
                                            Resize
                                        </Button>
                                    </form>
                                    {resizeError.length > 0 && (
                                        <Alert variant="destructive" onDismiss={() => setResizeError([])}>
                                            <AlertDescription>{resizeError.join(' ')}</AlertDescription>
                                        </Alert>
                                    )}
                                    {resizeJob && <ToolJobStatus job={resizeJob} videoId={video.id} />}
                                </CardContent>
                            </Card>
                        )}
                    </div>
                </>
            )}
        </AppLayout>
    );
}
