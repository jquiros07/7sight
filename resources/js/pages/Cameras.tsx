import { Circle, Loader2, Pencil, Plus, Trash2, Video } from 'lucide-react';
import { HSOverlay } from 'preline';
import { useEffect, useState } from 'react';
import { useLocation, useNavigate } from 'react-router-dom';
import { CameraPlayer } from '@/components/CameraPlayer';
import { AppLayout } from '@/components/AppLayout';
import { ActionButton } from '@/components/ui/action-button';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { ConfirmDialog } from '@/components/ui/confirm-dialog';
import { api } from '../lib/api';
import { getErrorMessages } from '../lib/errors';

const MAX_CAMERAS = 5;
const RECORDING_DURATION_OPTIONS = [3, 5, 15, 30, 60, 180, 300, 480];

function formatDurationMinutes(minutes: number): string {
    return minutes % 60 === 0 ? `${minutes / 60}h` : `${minutes}min`;
}

type Camera = {
    id: number;
    name: string;
    location: string | null;
    workspace: string | null;
    created_by: string | null;
    created_at: string;
    is_live: boolean;
    hls_url: string;
    active_recording_ends_at: string | null;
};

export default function Cameras() {
    const navigate = useNavigate();
    const location = useLocation();
    const [cameras, setCameras] = useState<Camera[]>([]);
    const [loading, setLoading] = useState(true);
    const [listError, setListError] = useState<string[]>([]);
    const [statusMessage, setStatusMessage] = useState<string | null>(
        (location.state as { message?: string } | null)?.message ?? null,
    );

    const [deleteTarget, setDeleteTarget] = useState<Camera | null>(null);
    const [deleting, setDeleting] = useState(false);

    const [recordingDurations, setRecordingDurations] = useState<Record<number, number>>({});
    const [startingRecordingId, setStartingRecordingId] = useState<number | null>(null);

    useEffect(() => {
        if (location.state) {
            navigate(location.pathname, { replace: true, state: null });
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    const STATUS_REFRESH_INTERVAL_MS = 10_000;

    // `silent` skips the loading spinner for background polls, so a periodic
    // status refresh doesn't interrupt whatever's already playing in each
    // camera tile - only the initial mount shows it.
    function load(silent = false) {
        if (!silent) setLoading(true);
        api.get<{ data: Camera[] }>('/api/cameras')
            .then((res) => {
                setCameras(res.data.data);
                setListError([]);
            })
            .catch((err) => setListError(getErrorMessages(err)))
            .finally(() => {
                if (!silent) setLoading(false);
            });
    }

    useEffect(() => {
        load();
    }, []);

    useEffect(() => {
        const interval = setInterval(() => load(true), STATUS_REFRESH_INTERVAL_MS);
        return () => clearInterval(interval);
    }, []);

    useEffect(() => {
        window.HSStaticMethods.autoInit();
    }, [cameras]);

    function openDeleteDialog(camera: Camera) {
        setDeleteTarget(camera);
        HSOverlay.open('#confirm-delete-camera');
    }

    async function startRecording(camera: Camera) {
        setStartingRecordingId(camera.id);
        try {
            await api.post(`/api/cameras/${camera.id}/recordings`, {
                duration_minutes: recordingDurations[camera.id] ?? RECORDING_DURATION_OPTIONS[0],
            });
            setStatusMessage(`Recording started for ${camera.name}.`);
            setListError([]);
            load();
        } catch (err) {
            setStatusMessage(null);
            setListError(getErrorMessages(err));
        } finally {
            setStartingRecordingId(null);
        }
    }

    async function confirmDelete() {
        if (!deleteTarget) return;
        setDeleting(true);
        try {
            await api.delete(`/api/cameras/${deleteTarget.id}`);
            HSOverlay.close('#confirm-delete-camera');
            setStatusMessage('Camera removed.');
            setListError([]);
            load();
        } catch (err) {
            setStatusMessage(null);
            setListError(getErrorMessages(err));
        } finally {
            setDeleting(false);
        }
    }

    return (
        <AppLayout active="cameras">
            <div className="flex items-center justify-between">
                <h1 className="font-heading text-2xl font-medium">Cameras</h1>
                <Button onClick={() => navigate('/cameras/create')} disabled={cameras.length >= MAX_CAMERAS}>
                    New
                    <Plus className="size-4" strokeWidth={1.75} />
                </Button>
            </div>
            <p className="mt-1 text-sm text-muted-foreground-1">
                {cameras.length} of {MAX_CAMERAS} cameras registered.
            </p>

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

            {!loading && cameras.length === 0 && listError.length === 0 && (
                <Card className="mt-6 p-6 text-center text-muted-foreground-1">
                    No cameras registered yet.
                </Card>
            )}

            {!loading && cameras.length > 0 && (
                <div className="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    {cameras.map((camera) => (
                        <Card key={camera.id} className="overflow-hidden">
                            <CameraPlayer hlsUrl={camera.hls_url} isLive={camera.is_live} name={camera.name} />
                            <div className="flex items-center justify-between p-3">
                                <div>
                                    <p className="font-medium text-foreground">{camera.name}</p>
                                    <p className="text-xs text-muted-foreground-1">
                                        {camera.location || '—'}
                                        {camera.workspace && ` · ${camera.workspace}`}
                                    </p>
                                </div>
                                <div className="flex gap-1">
                                    <ActionButton
                                        icon={<Video className="size-4" strokeWidth={1.75} />}
                                        label="Recordings"
                                        ariaLabel={`Recordings for ${camera.name}`}
                                        onClick={() => navigate(`/cameras/${camera.id}/recordings`)}
                                        hoverClassName="hover:text-primary"
                                    />
                                    <ActionButton
                                        icon={<Pencil className="size-4" strokeWidth={1.75} />}
                                        label="Edit"
                                        ariaLabel={`Edit ${camera.name}`}
                                        onClick={() => navigate(`/cameras/${camera.id}/edit`)}
                                        hoverClassName="hover:text-primary"
                                    />
                                    <ActionButton
                                        icon={<Trash2 className="size-4" strokeWidth={1.75} />}
                                        label="Delete"
                                        ariaLabel={`Delete ${camera.name}`}
                                        onClick={() => openDeleteDialog(camera)}
                                        hoverClassName="hover:text-destructive"
                                    />
                                </div>
                            </div>
                            <div className="flex items-center gap-2 border-t border-layer-line px-3 py-2">
                                {camera.active_recording_ends_at ? (
                                    <span className="flex items-center gap-1.5 text-xs text-muted-foreground-1">
                                        <Circle className="size-2 fill-destructive text-destructive" />
                                        Recording — ends {new Date(camera.active_recording_ends_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}
                                    </span>
                                ) : (
                                    <>
                                        <select
                                            value={recordingDurations[camera.id] ?? RECORDING_DURATION_OPTIONS[0]}
                                            onChange={(e) =>
                                                setRecordingDurations((current) => ({ ...current, [camera.id]: Number(e.target.value) }))
                                            }
                                            className="rounded-lg border-layer-line bg-layer py-1 pl-2 pr-8 text-xs text-foreground focus:border-primary-focus focus:ring-primary-focus"
                                        >
                                            {RECORDING_DURATION_OPTIONS.map((minutes) => (
                                                <option key={minutes} value={minutes}>
                                                    {formatDurationMinutes(minutes)}
                                                </option>
                                            ))}
                                        </select>
                                        <Button
                                            variant="secondary"
                                            className="px-2 py-1 text-xs"
                                            disabled={startingRecordingId === camera.id}
                                            onClick={() => startRecording(camera)}
                                        >
                                            {startingRecordingId === camera.id ? 'Starting…' : 'Record'}
                                        </Button>
                                    </>
                                )}
                            </div>
                        </Card>
                    ))}
                </div>
            )}

            <ConfirmDialog
                id="confirm-delete-camera"
                title="Delete camera"
                description={`Delete "${deleteTarget?.name}"? This stops its live stream, and you'll lose access to all of its recordings (clips already created from them are unaffected). This cannot be undone.`}
                confirmLabel={deleting ? 'Deleting…' : 'Delete'}
                confirmIcon={<Trash2 className="size-4" strokeWidth={1.75} />}
                confirmDisabled={deleting}
                variant="destructive"
                onConfirm={confirmDelete}
            />
        </AppLayout>
    );
}
