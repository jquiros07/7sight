import { FormEvent, useEffect, useState } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import { AppLayout } from '@/components/AppLayout';
import { AnalysisSettingsFields } from '@/components/AnalysisSettingsFields';
import { useAnalysisSettings } from '@/hooks/useAnalysisSettings';
import { api } from '../lib/api';
import { getErrorMessages } from '../lib/errors';
import { Download, Loader2, Scissors, Square } from 'lucide-react';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Recording = {
    id: number;
    status: 'recording' | 'completed' | 'failed' | 'cancelled';
    duration_minutes: number;
    duration_seconds: number | null;
    started_at: string | null;
    ends_at: string | null;
    error_message: string | null;
    cancel_requested: boolean;
};

function formatDuration(seconds: number | null): string {
    if (seconds === null) return '—';
    const h = Math.floor(seconds / 3600);
    const m = Math.floor((seconds % 3600) / 60);
    return h > 0 ? `${h}h ${m}m` : `${m}m`;
}

function formatDurationMinutes(minutes: number): string {
    return minutes % 60 === 0 ? `${minutes / 60}h` : `${minutes}min`;
}

const STATUS_LABELS: Record<Recording['status'], string> = {
    recording: 'Recording',
    completed: 'Completed',
    failed: 'Failed',
    cancelled: 'Cancelled',
};

export default function CameraRecordings() {
    const { id } = useParams<{ id: string }>();
    const navigate = useNavigate();

    const [cameraName, setCameraName] = useState('');
    const [recordings, setRecordings] = useState<Recording[]>([]);
    const [loading, setLoading] = useState(true);
    const [listError, setListError] = useState<string[]>([]);
    const [statusMessage, setStatusMessage] = useState<string | null>(null);
    const [cancellingId, setCancellingId] = useState<number | null>(null);
    const [clippingId, setClippingId] = useState<number | null>(null);

    function load(silent = false) {
        if (!silent) setLoading(true);
        Promise.all([
            api.get<{ name: string }>(`/api/cameras/${id}`),
            api.get<{ data: Recording[] }>(`/api/cameras/${id}/recordings`),
        ])
            .then(([cameraRes, recordingsRes]) => {
                setCameraName(cameraRes.data.name);
                setRecordings(recordingsRes.data.data);
                setListError([]);
            })
            .catch((err) => setListError(getErrorMessages(err)))
            .finally(() => {
                if (!silent) setLoading(false);
            });
    }

    useEffect(() => {
        load();
    }, [id]);

    useEffect(() => {
        const interval = setInterval(() => load(true), 10_000);
        return () => clearInterval(interval);
    }, [id]);

    async function cancelRecording(recording: Recording) {
        setCancellingId(recording.id);
        try {
            await api.post(`/api/cameras/${id}/recordings/${recording.id}/cancel`);
            // Cancellation is asynchronous: this only flags the recording, the
            // job stops ffmpeg and finalizes the file within a few seconds. The
            // ambient 10s poll will pick up the final "cancelled" status.
            setStatusMessage('Cancellation requested — stopping shortly.');
            load();
        } catch (err) {
            setListError(getErrorMessages(err));
        } finally {
            setCancellingId(null);
        }
    }

    return (
        <AppLayout active="cameras">
            <div className="flex items-center justify-between">
                <h1 className="font-heading text-2xl font-medium">{cameraName ? `${cameraName} — Recordings` : 'Recordings'}</h1>
                <Button variant="secondary" onClick={() => navigate('/cameras')}>
                    Back to cameras
                </Button>
            </div>

            {statusMessage && listError.length === 0 && (
                <Alert variant="success" className="mt-4" onDismiss={() => setStatusMessage(null)}>
                    <AlertDescription>{statusMessage}</AlertDescription>
                </Alert>
            )}

            {listError.length > 0 && (
                <Alert variant="destructive" className="mt-4" onDismiss={() => setListError([])}>
                    <AlertDescription>
                        <ul className="list-disc space-y-1 pl-4">
                            {listError.map((message) => (
                                <li key={message}>{message}</li>
                            ))}
                        </ul>
                    </AlertDescription>
                </Alert>
            )}

            {loading && (
                <div className="mt-6 flex items-center justify-center gap-2 text-muted-foreground-1">
                    <Loader2 className="size-4 animate-spin" strokeWidth={1.75} />
                    Loading…
                </div>
            )}

            {!loading && recordings.length === 0 && listError.length === 0 && (
                <Card className="mt-6 p-6 text-center text-muted-foreground-1">No recordings yet.</Card>
            )}

            {!loading && recordings.length > 0 && (
                <div className="mt-6 flex flex-col gap-3">
                    {recordings.map((recording) => (
                        <Card key={recording.id}>
                            <div className="flex items-center justify-between p-4">
                                <div>
                                    <p className="font-medium text-foreground">
                                        {STATUS_LABELS[recording.status]} · {formatDurationMinutes(recording.duration_minutes)} requested
                                    </p>
                                    <p className="text-xs text-muted-foreground-1">
                                        {recording.started_at ? new Date(recording.started_at).toLocaleString() : '—'}
                                        {recording.status === 'completed' && ` · ${formatDuration(recording.duration_seconds)}`}
                                        {recording.error_message && ` · ${recording.error_message}`}
                                    </p>
                                </div>
                                <div className="flex gap-2">
                                    {recording.status === 'recording' && recording.cancel_requested && (
                                        <span className="flex items-center gap-1.5 text-xs text-muted-foreground-1">
                                            <Loader2 className="size-3.5 animate-spin" strokeWidth={1.75} />
                                            Stopping…
                                        </span>
                                    )}
                                    {recording.status === 'recording' && !recording.cancel_requested && (
                                        <Button
                                            variant="secondary"
                                            disabled={cancellingId === recording.id}
                                            onClick={() => cancelRecording(recording)}
                                        >
                                            <Square className="size-4" strokeWidth={1.75} fill="currentColor" />
                                            {cancellingId === recording.id ? 'Cancelling…' : 'Cancel'}
                                        </Button>
                                    )}
                                    {recording.status === 'completed' && (
                                        <>
                                            <a
                                                href={`/api/cameras/${id}/recordings/${recording.id}/download`}
                                                className="inline-flex w-fit items-center justify-center gap-x-2 rounded-lg border border-secondary-line bg-secondary px-3 py-1.5 text-sm font-medium text-secondary-foreground hover:bg-secondary-hover"
                                            >
                                                <Download className="size-4" strokeWidth={1.75} />
                                                Download
                                            </a>
                                            <Button
                                                variant="secondary"
                                                onClick={() => setClippingId(clippingId === recording.id ? null : recording.id)}
                                            >
                                                <Scissors className="size-4" strokeWidth={1.75} />
                                                Create clip
                                            </Button>
                                        </>
                                    )}
                                </div>
                            </div>
                            {clippingId === recording.id && (
                                <ClipForm
                                    cameraId={id!}
                                    recording={recording}
                                    onCreated={() => {
                                        setClippingId(null);
                                        setStatusMessage('Clip created — analysis will appear in the video results once ready.');
                                    }}
                                    onCancel={() => setClippingId(null)}
                                />
                            )}
                        </Card>
                    ))}
                </div>
            )}
        </AppLayout>
    );
}

function ClipForm({
    cameraId,
    recording,
    onCreated,
    onCancel,
}: {
    cameraId: string;
    recording: Recording;
    onCreated: () => void;
    onCancel: () => void;
}) {
    const [title, setTitle] = useState('');
    const [startSeconds, setStartSeconds] = useState(0);
    const [endSeconds, setEndSeconds] = useState(Math.min(recording.duration_seconds ?? 0, 15 * 60));
    const [submitting, setSubmitting] = useState(false);
    const [formErrors, setFormErrors] = useState<string[]>([]);
    const analysisSettings = useAnalysisSettings();

    // This form (and its AnalysisSettingsFields select) only mounts when the
    // user clicks "Create clip" - a local state change, not a route change,
    // so Router.tsx's global autoInit() (which only fires on navigation)
    // never reaches it. Without this, the hidden native <select> never gets
    // replaced by Preline's visible widget.
    useEffect(() => {
        window.HSStaticMethods.autoInit();
    }, []);

    async function handleSubmit(e: FormEvent) {
        e.preventDefault();
        setFormErrors([]);
        setSubmitting(true);
        try {
            await api.post(`/api/cameras/${cameraId}/recordings/${recording.id}/clip`, {
                title,
                start_seconds: startSeconds,
                end_seconds: endSeconds,
                ...analysisSettings.toPayload(),
            });
            onCreated();
        } catch (err) {
            setFormErrors(getErrorMessages(err));
        } finally {
            setSubmitting(false);
        }
    }

    return (
        <form onSubmit={handleSubmit} className="flex flex-col gap-4 border-t border-layer-line p-4">
            {formErrors.length > 0 && (
                <Alert variant="destructive" onDismiss={() => setFormErrors([])}>
                    <AlertDescription>
                        <ul className="list-disc space-y-1 pl-4">
                            {formErrors.map((message) => (
                                <li key={message}>{message}</li>
                            ))}
                        </ul>
                    </AlertDescription>
                </Alert>
            )}
            <div className="flex flex-col gap-1.5">
                <Label htmlFor={`clip-title-${recording.id}`}>Title</Label>
                <Input id={`clip-title-${recording.id}`} value={title} onChange={(e) => setTitle(e.target.value)} />
            </div>
            <div className="grid grid-cols-2 gap-4">
                <div className="flex flex-col gap-1.5">
                    <Label htmlFor={`clip-start-${recording.id}`}>Start (seconds)</Label>
                    <Input
                        id={`clip-start-${recording.id}`}
                        type="number"
                        min={0}
                        max={recording.duration_seconds ?? undefined}
                        value={startSeconds}
                        onChange={(e) => setStartSeconds(Number(e.target.value))}
                    />
                </div>
                <div className="flex flex-col gap-1.5">
                    <Label htmlFor={`clip-end-${recording.id}`}>End (seconds)</Label>
                    <Input
                        id={`clip-end-${recording.id}`}
                        type="number"
                        min={0}
                        max={recording.duration_seconds ?? undefined}
                        value={endSeconds}
                        onChange={(e) => setEndSeconds(Number(e.target.value))}
                    />
                </div>
            </div>
            <p className="text-xs text-muted-foreground-1">Clips must be 15 minutes or shorter, and 500MB or smaller.</p>
            <AnalysisSettingsFields {...analysisSettings} />
            <div className="flex justify-end gap-2">
                <Button type="button" variant="secondary" onClick={onCancel}>
                    Cancel
                </Button>
                <Button type="submit" disabled={submitting}>
                    {submitting ? 'Creating…' : 'Create clip'}
                </Button>
            </div>
        </form>
    );
}
