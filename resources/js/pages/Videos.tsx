import { useEffect, useState } from 'react';
import { useLocation, useNavigate } from 'react-router-dom';
import { AppLayout } from '@/components/AppLayout';
import { Loader2, Pencil, Plus, Sparkles, Trash2 } from 'lucide-react';
import { HSOverlay } from 'preline';
import { api } from '../lib/api';
import { getErrorMessages } from '../lib/errors';
import { cn } from '../lib/utils';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { ConfirmDialog } from '@/components/ui/confirm-dialog';

type VideoStatus = 'uploaded' | 'processing' | 'ready' | 'failed';

type Video = {
    id: number;
    title: string;
    status: VideoStatus;
    size: number;
    duration_seconds: number | null;
    created_at: string;
    workspace: { id: number; name: string };
};

type PaginatedVideos = {
    data: Video[];
    current_page: number;
    last_page: number;
    total: number;
};

type SortField = 'title' | 'created_at';

function formatDuration(totalSeconds: number | null): string {
    if (totalSeconds === null) return '—';
    const hours = Math.floor(totalSeconds / 3600);
    const minutes = Math.floor((totalSeconds % 3600) / 60);
    const seconds = Math.floor(totalSeconds % 60);
    if (hours > 0) {
        return `${hours}:${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
    }
    return `${minutes}:${String(seconds).padStart(2, '0')}`;
}

function formatFileSize(bytes: number): string {
    if (bytes === 0) return '0 B';
    const units = ['B', 'KB', 'MB', 'GB'];
    const i = Math.min(Math.floor(Math.log(bytes) / Math.log(1024)), units.length - 1);
    return `${(bytes / 1024 ** i).toFixed(i === 0 ? 0 : 1)} ${units[i]}`;
}

const STATUS_BADGES: Record<VideoStatus, { label: string; className: string; icon?: 'dot' | 'spinner' }> = {
    uploaded: {
        label: 'Uploaded',
        className: 'bg-violet-100 text-violet-800 dark:bg-violet-500/20 dark:text-violet-400',
        icon: 'dot',
    },
    processing: {
        label: 'Processing',
        className: 'bg-amber-100 text-amber-800 dark:bg-amber-500/20 dark:text-amber-400',
        icon: 'spinner',
    },
    ready: { label: 'Ready to analyze', className: 'bg-primary/10 text-primary', icon: 'dot' },
    failed: { label: 'Failed', className: 'bg-red-100 text-red-800 dark:bg-red-500/20 dark:text-red-400', icon: 'dot' },
};

function StatusBadge({ status }: { status: VideoStatus }) {
    const badge = STATUS_BADGES[status];

    return (
        <span className={cn('inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium', badge.className)}>
            {badge.icon === 'spinner' ? (
                <Loader2 className="size-3 animate-spin" strokeWidth={2} />
            ) : (
                <span className="size-1.5 rounded-full bg-current" />
            )}
            {badge.label}
        </span>
    );
}

function SortButton({
    label,
    field,
    sort,
    direction,
    onSort,
}: {
    label: string;
    field: SortField;
    sort: SortField;
    direction: 'asc' | 'desc';
    onSort: (field: SortField) => void;
}) {
    const active = sort === field;

    return (
        <button
            type="button"
            onClick={() => onSort(field)}
            className={cn(
                'flex items-center gap-1 text-xs font-semibold uppercase tracking-wide',
                active ? 'text-primary' : 'text-muted-foreground-1 hover:text-foreground',
            )}
        >
            {label}
            {active && <span>{direction === 'asc' ? '↑' : '↓'}</span>}
        </button>
    );
}

export default function Videos() {
    const navigate = useNavigate();
    const location = useLocation();

    const [videos, setVideos] = useState<PaginatedVideos | null>(null);
    const [page, setPage] = useState(1);
    const [sort, setSort] = useState<SortField>('created_at');
    const [direction, setDirection] = useState<'asc' | 'desc'>('desc');
    const [loading, setLoading] = useState(true);
    const [listError, setListError] = useState<string[]>([]);
    const [statusMessage, setStatusMessage] = useState<string | null>(
        (location.state as { message?: string } | null)?.message ?? null,
    );

    const [queuedIds, setQueuedIds] = useState<number[]>([]);
    const [showAnalyzeNote, setShowAnalyzeNote] = useState(false);

    const [deleteTarget, setDeleteTarget] = useState<Video | null>(null);
    const [deleting, setDeleting] = useState(false);

    useEffect(() => {
        if (location.state) {
            navigate(location.pathname, { replace: true, state: null });
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    function load() {
        setLoading(true);
        api.get<PaginatedVideos>('/api/videos', { params: { page, sort, direction, per_page: 10 } })
            .then((res) => {
                setVideos(res.data);
                setListError([]);
            })
            .catch((err) => setListError(getErrorMessages(err)))
            .finally(() => setLoading(false));
    }

    useEffect(() => {
        load();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [page, sort, direction]);

    function handleSort(field: SortField) {
        if (field === sort) {
            setDirection((d) => (d === 'asc' ? 'desc' : 'asc'));
        } else {
            setSort(field);
            setDirection('asc');
        }
        setPage(1);
    }

    function handleAnalyze(id: number) {
        // UI only for now — wire this up to the analysis pipeline once it exists.
        setQueuedIds((current) => [...current, id]);
        setShowAnalyzeNote(true);
    }

    function openDeleteDialog(video: Video) {
        setDeleteTarget(video);
        HSOverlay.open('#confirm-delete-video');
    }

    async function confirmDelete() {
        if (!deleteTarget) return;
        setDeleting(true);
        try {
            await api.delete(`/api/videos/${deleteTarget.id}`);
            HSOverlay.close('#confirm-delete-video');
            setStatusMessage('Video deleted.');
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
        <AppLayout active="videos">
            <div className="flex items-center justify-between">
                <h1 className="font-heading text-2xl font-medium">Videos</h1>
                <Button onClick={() => navigate('/videos/upload')}>
                    Upload
                    <Plus className="size-4" strokeWidth={1.75} />
                </Button>
            </div>

            {showAnalyzeNote && (
                <Alert className="mt-4" onDismiss={() => setShowAnalyzeNote(false)}>
                    <AlertDescription>Queued for analysis — this isn't wired up to the analysis pipeline yet.</AlertDescription>
                </Alert>
            )}

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

            <Card className="mt-4 overflow-hidden">
                <div className="overflow-x-auto">
                    <table className="w-full text-left text-sm">
                        <thead className="border-b border-card-line bg-surface/50">
                            <tr>
                                <th className="px-4 py-3">
                                    <SortButton label="Title" field="title" sort={sort} direction={direction} onSort={handleSort} />
                                </th>
                                <th className="px-4 py-3">Workspace</th>
                                <th className="px-4 py-3">Status</th>
                                <th className="px-4 py-3">Duration</th>
                                <th className="px-4 py-3">Size</th>
                                <th className="px-4 py-3">
                                    <SortButton label="Uploaded" field="created_at" sort={sort} direction={direction} onSort={handleSort} />
                                </th>
                                <th className="px-4 py-3 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-card-line">
                            {loading && (
                                <tr>
                                    <td colSpan={7} className="px-4 py-6 text-center text-muted-foreground-1">
                                        Loading…
                                    </td>
                                </tr>
                            )}
                            {!loading && videos?.data.length === 0 && (
                                <tr>
                                    <td colSpan={7} className="px-4 py-6 text-center text-muted-foreground-1">
                                        No videos yet.
                                    </td>
                                </tr>
                            )}
                            {!loading &&
                                videos?.data.map((video) => {
                                    const queued = queuedIds.includes(video.id);

                                    return (
                                        <tr key={video.id}>
                                            <td className="px-4 py-3 font-medium text-foreground">{video.title}</td>
                                            <td className="px-4 py-3 text-muted-foreground-1">{video.workspace.name}</td>
                                            <td className="px-4 py-3">
                                                <StatusBadge status={video.status} />
                                            </td>
                                            <td className="px-4 py-3 text-muted-foreground-1">{formatDuration(video.duration_seconds)}</td>
                                            <td className="px-4 py-3 text-muted-foreground-1">{formatFileSize(video.size)}</td>
                                            <td className="px-4 py-3 text-muted-foreground-1">
                                                {new Date(video.created_at).toLocaleDateString()}
                                            </td>
                                            <td className="px-4 py-3">
                                                <div className="flex items-center justify-end gap-1">
                                                    {video.status === 'ready' && (
                                                        <Button
                                                            variant="secondary"
                                                            disabled={queued}
                                                            onClick={() => handleAnalyze(video.id)}
                                                            className="py-2 px-3 text-xs"
                                                        >
                                                            {queued ? 'Queued' : 'Analyze'}
                                                            <Sparkles className="size-3.5" strokeWidth={1.75} />
                                                        </Button>
                                                    )}
                                                    <button
                                                        type="button"
                                                        onClick={() => navigate(`/videos/${video.id}/edit`)}
                                                        aria-label={`Edit ${video.title}`}
                                                        title="Edit"
                                                        className="flex size-8 items-center justify-center rounded-lg text-muted-foreground-1 hover:bg-layer-hover hover:text-primary"
                                                    >
                                                        <Pencil className="size-4" strokeWidth={1.75} />
                                                    </button>
                                                    <button
                                                        type="button"
                                                        onClick={() => openDeleteDialog(video)}
                                                        aria-label={`Delete ${video.title}`}
                                                        title="Delete"
                                                        className="flex size-8 items-center justify-center rounded-lg text-muted-foreground-1 hover:bg-layer-hover hover:text-destructive"
                                                    >
                                                        <Trash2 className="size-4" strokeWidth={1.75} />
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    );
                                })}
                        </tbody>
                    </table>
                </div>
            </Card>

            {videos && videos.last_page > 1 && (
                <div className="mt-4 flex items-center justify-between text-sm">
                    <p className="text-muted-foreground-1">
                        Page {videos.current_page} of {videos.last_page} ({videos.total} total)
                    </p>
                    <div className="flex gap-2">
                        <Button variant="secondary" disabled={page <= 1} onClick={() => setPage((p) => p - 1)}>
                            Previous
                        </Button>
                        <Button variant="secondary" disabled={page >= videos.last_page} onClick={() => setPage((p) => p + 1)}>
                            Next
                        </Button>
                    </div>
                </div>
            )}

            <ConfirmDialog
                id="confirm-delete-video"
                title="Delete video"
                description={`Delete "${deleteTarget?.title}"? This cannot be undone.`}
                confirmLabel={deleting ? 'Deleting…' : 'Delete'}
                confirmIcon={<Trash2 className="size-4" strokeWidth={1.75} />}
                confirmDisabled={deleting}
                variant="destructive"
                onConfirm={confirmDelete}
            />
        </AppLayout>
    );
}
