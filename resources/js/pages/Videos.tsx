import { FormEvent, useEffect, useState } from 'react';
import { useLocation, useNavigate } from 'react-router-dom';
import { AppLayout } from '@/components/AppLayout';
import { Eye, Loader2, Pencil, Plus, Search, SlidersHorizontal, Sparkles, Trash2, X } from 'lucide-react';
import { HSOverlay } from 'preline';
import { api } from '../lib/api';
import { getErrorMessages } from '../lib/errors';
import { cn } from '../lib/utils';
import { ActionButton } from '@/components/ui/action-button';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { ConfirmDialog } from '@/components/ui/confirm-dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type VideoStatus = 'uploaded' | 'processing' | 'ready' | 'failed';

type Video = {
    id: number;
    title: string;
    status: VideoStatus;
    size: number;
    duration_seconds: number | null;
    created_at: string;
    workspace: { id: number; name: string };
    has_completed_analysis: boolean;
};

type PaginatedVideos = {
    data: Video[];
    current_page: number;
    last_page: number;
    total: number;
};

type SortField = 'title' | 'created_at';

type VideoFilters = {
    search: string;
    dateFrom: string;
    dateTo: string;
    durationMin: string;
    durationMax: string;
    sizeMin: string;
    sizeMax: string;
};

const EMPTY_FILTERS: VideoFilters = {
    search: '',
    dateFrom: '',
    dateTo: '',
    durationMin: '',
    durationMax: '',
    sizeMin: '',
    sizeMax: '',
};

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

const ANALYZED_BADGE = {
    label: 'Analyzed',
    className: 'bg-green-100 text-green-800 dark:bg-green-500/20 dark:text-green-400',
    icon: 'dot' as const,
};

function StatusBadge({ status, analyzed }: { status: VideoStatus; analyzed: boolean }) {
    const badge = status === 'ready' && analyzed ? ANALYZED_BADGE : STATUS_BADGES[status];

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

function RangeField({
    legend,
    fromLabel,
    toLabel,
    fromValue,
    toValue,
    onFromChange,
    onToChange,
    type = 'number',
    inputClassName = 'w-20',
}: {
    legend: string;
    fromLabel: string;
    toLabel: string;
    fromValue: string;
    toValue: string;
    onFromChange: (value: string) => void;
    onToChange: (value: string) => void;
    type?: 'number' | 'date';
    inputClassName?: string;
}) {
    return (
        <div>
            <Label className="mb-1 block text-xs font-semibold uppercase tracking-wide text-muted-foreground-1">{legend}</Label>
            <div className="flex items-center gap-1.5">
                <Input
                    type={type}
                    min={type === 'number' ? '0' : undefined}
                    step={type === 'number' ? '0.1' : undefined}
                    className={inputClassName}
                    value={fromValue}
                    onChange={(e) => onFromChange(e.target.value)}
                    aria-label={fromLabel}
                />
                <span className="text-muted-foreground-1">–</span>
                <Input
                    type={type}
                    min={type === 'number' ? '0' : undefined}
                    step={type === 'number' ? '0.1' : undefined}
                    className={inputClassName}
                    value={toValue}
                    onChange={(e) => onToChange(e.target.value)}
                    aria-label={toLabel}
                />
            </div>
        </div>
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
    const [filtersInput, setFiltersInput] = useState<VideoFilters>(EMPTY_FILTERS);
    const [filters, setFilters] = useState<VideoFilters>(EMPTY_FILTERS);
    const [showFilters, setShowFilters] = useState(false);
    const [loading, setLoading] = useState(true);
    const [listError, setListError] = useState<string[]>([]);
    const [statusMessage, setStatusMessage] = useState<string | null>(
        (location.state as { message?: string } | null)?.message ?? null,
    );

    const [analyzingIds, setAnalyzingIds] = useState<number[]>([]);

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
        api.get<PaginatedVideos>('/api/videos', {
            params: {
                page,
                sort,
                direction,
                per_page: 10,
                search: filters.search || undefined,
                date_from: filters.dateFrom || undefined,
                date_to: filters.dateTo || undefined,
                duration_min: filters.durationMin ? Math.round(Number(filters.durationMin) * 60) : undefined,
                duration_max: filters.durationMax ? Math.round(Number(filters.durationMax) * 60) : undefined,
                size_min: filters.sizeMin ? Math.round(Number(filters.sizeMin) * 1024 * 1024) : undefined,
                size_max: filters.sizeMax ? Math.round(Number(filters.sizeMax) * 1024 * 1024) : undefined,
            },
        })
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
    }, [page, sort, direction, filters]);

    function handleFilterSubmit(event: FormEvent) {
        event.preventDefault();
        setFilters(filtersInput);
        setPage(1);
    }

    function handleClearFilters() {
        setFiltersInput(EMPTY_FILTERS);
        setFilters(EMPTY_FILTERS);
        setPage(1);
    }

    const hasActiveFilters = Object.values(filtersInput).some((value) => value !== '');
    const hasActiveRangeFilters = Object.entries(filtersInput).some(([key, value]) => key !== 'search' && value !== '');

    // Rows (and their tooltips) render after `videos` loads, which is after
    // Router's pathname-based autoInit() already ran. Re-init once they exist.
    useEffect(() => {
        if (videos) {
            window.HSStaticMethods.autoInit();
        }
    }, [videos]);

    const hasProcessing = videos?.data.some((video) => video.status === 'processing') ?? false;

    // Poll while anything is analyzing so status badges update without a manual refresh.
    useEffect(() => {
        if (!hasProcessing) return;
        const interval = setInterval(load, 5000);
        return () => clearInterval(interval);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [hasProcessing]);

    function handleSort(field: SortField) {
        if (field === sort) {
            setDirection((d) => (d === 'asc' ? 'desc' : 'asc'));
        } else {
            setSort(field);
            setDirection('asc');
        }
        setPage(1);
    }

    async function handleAnalyze(id: number) {
        setAnalyzingIds((current) => [...current, id]);
        setListError([]);
        try {
            await api.post(`/api/videos/${id}/analyze`);
            load();
        } catch (err) {
            setListError(getErrorMessages(err));
        } finally {
            setAnalyzingIds((current) => current.filter((analyzingId) => analyzingId !== id));
        }
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

            <Card className="mt-4">
                <form onSubmit={handleFilterSubmit} className="p-4">
                    <div className="flex flex-wrap items-end gap-3">
                        <div className="min-w-[220px] flex-1">
                            <Label className="mb-1 block text-xs font-semibold uppercase tracking-wide text-muted-foreground-1">
                                Search
                            </Label>
                            <div className="relative">
                                <Search
                                    className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground-1"
                                    strokeWidth={1.75}
                                />
                                <Input
                                    type="search"
                                    placeholder="Title, workspace, or status…"
                                    value={filtersInput.search}
                                    onChange={(e) => setFiltersInput((f) => ({ ...f, search: e.target.value }))}
                                    className="pl-9"
                                    aria-label="Search videos"
                                />
                            </div>
                        </div>
                        <Button type="button" variant="secondary" onClick={() => setShowFilters((v) => !v)}>
                            <SlidersHorizontal className="size-4" strokeWidth={1.75} />
                            Filters
                            {hasActiveRangeFilters && <span className="size-1.5 rounded-full bg-primary" />}
                        </Button>
                        {hasActiveFilters && (
                            <Button type="button" variant="secondary" onClick={handleClearFilters}>
                                Clear
                                <X className="size-4" strokeWidth={1.75} />
                            </Button>
                        )}
                        <Button type="submit" variant="secondary">
                            Search
                            <Search className="size-4" strokeWidth={1.75} />
                        </Button>
                    </div>

                    {showFilters && (
                        <div className="mt-4 flex flex-wrap gap-x-6 gap-y-3 border-t border-card-line pt-4">
                            <RangeField
                                legend="Uploaded"
                                fromLabel="Uploaded from date"
                                toLabel="Uploaded to date"
                                fromValue={filtersInput.dateFrom}
                                toValue={filtersInput.dateTo}
                                onFromChange={(value) => setFiltersInput((f) => ({ ...f, dateFrom: value }))}
                                onToChange={(value) => setFiltersInput((f) => ({ ...f, dateTo: value }))}
                                type="date"
                                inputClassName="w-36"
                            />
                            <RangeField
                                legend="Duration (min)"
                                fromLabel="Minimum duration in minutes"
                                toLabel="Maximum duration in minutes"
                                fromValue={filtersInput.durationMin}
                                toValue={filtersInput.durationMax}
                                onFromChange={(value) => setFiltersInput((f) => ({ ...f, durationMin: value }))}
                                onToChange={(value) => setFiltersInput((f) => ({ ...f, durationMax: value }))}
                            />
                            <RangeField
                                legend="Size (MB)"
                                fromLabel="Minimum size in megabytes"
                                toLabel="Maximum size in megabytes"
                                fromValue={filtersInput.sizeMin}
                                toValue={filtersInput.sizeMax}
                                onFromChange={(value) => setFiltersInput((f) => ({ ...f, sizeMin: value }))}
                                onToChange={(value) => setFiltersInput((f) => ({ ...f, sizeMax: value }))}
                            />
                        </div>
                    )}
                </form>
            </Card>

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
                                    const analyzing = analyzingIds.includes(video.id);

                                    return (
                                        <tr key={video.id}>
                                            <td className="px-4 py-3 font-medium text-foreground">{video.title}</td>
                                            <td className="px-4 py-3 text-muted-foreground-1">{video.workspace.name}</td>
                                            <td className="px-4 py-3">
                                                <StatusBadge status={video.status} analyzed={video.has_completed_analysis} />
                                            </td>
                                            <td className="px-4 py-3 text-muted-foreground-1">{formatDuration(video.duration_seconds)}</td>
                                            <td className="px-4 py-3 text-muted-foreground-1">{formatFileSize(video.size)}</td>
                                            <td className="px-4 py-3 text-muted-foreground-1">
                                                {new Date(video.created_at).toLocaleDateString()}
                                            </td>
                                            <td className="px-4 py-3">
                                                <div className="flex items-center justify-end gap-1">
                                                    {(video.status === 'uploaded' ||
                                                        video.status === 'ready' ||
                                                        video.status === 'failed') && (
                                                        <ActionButton
                                                            icon={<Sparkles className="size-4" strokeWidth={1.75} />}
                                                            label={analyzing ? 'Queuing…' : 'Analyze'}
                                                            ariaLabel={`Analyze ${video.title}`}
                                                            onClick={() => handleAnalyze(video.id)}
                                                            disabled={analyzing}
                                                            hoverClassName="hover:text-primary"
                                                        />
                                                    )}
                                                    <ActionButton
                                                        icon={<Eye className="size-4" strokeWidth={1.75} />}
                                                        label="View results"
                                                        ariaLabel={`View results for ${video.title}`}
                                                        onClick={() => navigate(`/videos/${video.id}/results`)}
                                                        hoverClassName="hover:text-primary"
                                                    />
                                                    <ActionButton
                                                        icon={<Pencil className="size-4" strokeWidth={1.75} />}
                                                        label="Edit"
                                                        ariaLabel={`Edit ${video.title}`}
                                                        onClick={() => navigate(`/videos/${video.id}/edit`)}
                                                        hoverClassName="hover:text-primary"
                                                    />
                                                    <ActionButton
                                                        icon={<Trash2 className="size-4" strokeWidth={1.75} />}
                                                        label="Delete"
                                                        ariaLabel={`Delete ${video.title}`}
                                                        onClick={() => openDeleteDialog(video)}
                                                        hoverClassName="hover:text-destructive"
                                                    />
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
