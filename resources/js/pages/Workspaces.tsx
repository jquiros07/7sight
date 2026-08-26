import { FormEvent, useEffect, useState } from 'react';
import { useLocation, useNavigate } from 'react-router-dom';
import { AppLayout } from '@/components/AppLayout';
import { LayoutDashboard, Loader2, Pencil, Plus, Search, SlidersHorizontal, Trash2, X } from 'lucide-react';
import { HSOverlay } from 'preline';
import { api } from '../lib/api';
import { useAuth } from '../lib/auth';
import { getErrorMessages } from '../lib/errors';
import { cn } from '../lib/utils';
import { ActionButton } from '@/components/ui/action-button';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { ConfirmDialog } from '@/components/ui/confirm-dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Role = 'owner' | 'admin' | 'member';

type Workspace = {
    id: number;
    owner_id: number;
    name: string;
    slug: string;
    description: string | null;
    created_at: string;
    pivot: { role: Role };
};

type PaginatedWorkspaces = {
    data: Workspace[];
    current_page: number;
    last_page: number;
    total: number;
};

type SortField = 'name' | 'created_at';

type WorkspaceFilters = {
    search: string;
    dateFrom: string;
    dateTo: string;
};

const EMPTY_FILTERS: WorkspaceFilters = { search: '', dateFrom: '', dateTo: '' };

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

export default function Workspaces() {
    const navigate = useNavigate();
    const location = useLocation();
    const { can } = useAuth();
    const [workspaces, setWorkspaces] = useState<PaginatedWorkspaces | null>(null);
    const [page, setPage] = useState(1);
    const [sort, setSort] = useState<SortField>('created_at');
    const [direction, setDirection] = useState<'asc' | 'desc'>('desc');
    const [filtersInput, setFiltersInput] = useState<WorkspaceFilters>(EMPTY_FILTERS);
    const [filters, setFilters] = useState<WorkspaceFilters>(EMPTY_FILTERS);
    const [showFilters, setShowFilters] = useState(false);
    const [loading, setLoading] = useState(true);
    const [listError, setListError] = useState<string[]>([]);
    const [statusMessage, setStatusMessage] = useState<string | null>(
        (location.state as { message?: string } | null)?.message ?? null,
    );

    const [deleteTarget, setDeleteTarget] = useState<Workspace | null>(null);
    const [deleting, setDeleting] = useState(false);

    useEffect(() => {
        if (location.state) {
            navigate(location.pathname, { replace: true, state: null });
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    function load() {
        setLoading(true);
        api.get<PaginatedWorkspaces>('/api/workspaces', {
            params: {
                page,
                sort,
                direction,
                per_page: 10,
                search: filters.search || undefined,
                date_from: filters.dateFrom || undefined,
                date_to: filters.dateTo || undefined,
            },
        })
            .then((res) => {
                setWorkspaces(res.data);
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

    // Rows (and their tooltips) render after `workspaces` loads, which is after
    // Router's pathname-based autoInit() already ran. Re-init once they exist.
    useEffect(() => {
        if (workspaces) {
            window.HSStaticMethods.autoInit();
        }
    }, [workspaces]);

    function handleSort(field: SortField) {
        if (field === sort) {
            setDirection((d) => (d === 'asc' ? 'desc' : 'asc'));
        } else {
            setSort(field);
            setDirection('asc');
        }
        setPage(1);
    }

    function openDeleteDialog(workspace: Workspace) {
        setDeleteTarget(workspace);
        HSOverlay.open('#confirm-delete-workspace');
    }

    async function confirmDelete() {
        if (!deleteTarget) return;
        setDeleting(true);
        try {
            await api.delete(`/api/workspaces/${deleteTarget.id}`);
            HSOverlay.close('#confirm-delete-workspace');
            setStatusMessage('Workspace deleted.');
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
        <AppLayout active="workspaces">
            <div className="flex items-center justify-between">
                <h1 className="font-heading text-2xl font-medium">Workspaces</h1>
                <Button onClick={() => navigate('/workspaces/create')}>
                    New
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
                                    placeholder="Name or description…"
                                    value={filtersInput.search}
                                    onChange={(e) => setFiltersInput((f) => ({ ...f, search: e.target.value }))}
                                    className="pl-9"
                                    aria-label="Search workspaces"
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
                        <div className="mt-4 border-t border-card-line pt-4">
                            <Label className="mb-1 block text-xs font-semibold uppercase tracking-wide text-muted-foreground-1">
                                Created
                            </Label>
                            <div className="flex items-center gap-1.5">
                                <Input
                                    type="date"
                                    className="w-36"
                                    value={filtersInput.dateFrom}
                                    onChange={(e) => setFiltersInput((f) => ({ ...f, dateFrom: e.target.value }))}
                                    aria-label="Created from date"
                                />
                                <span className="text-muted-foreground-1">–</span>
                                <Input
                                    type="date"
                                    className="w-36"
                                    value={filtersInput.dateTo}
                                    onChange={(e) => setFiltersInput((f) => ({ ...f, dateTo: e.target.value }))}
                                    aria-label="Created to date"
                                />
                            </div>
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
                                    <SortButton label="Name" field="name" sort={sort} direction={direction} onSort={handleSort} />
                                </th>
                                <th className="px-4 py-3">Description</th>
                                <th className="px-4 py-3">Role</th>
                                <th className="px-4 py-3">
                                    <SortButton label="Created" field="created_at" sort={sort} direction={direction} onSort={handleSort} />
                                </th>
                                <th className="px-4 py-3 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-card-line">
                            {loading && (
                                <tr>
                                    <td colSpan={5} className="px-4 py-6 text-center text-muted-foreground-1">
                                        <div className="flex items-center justify-center gap-2">
                                            <Loader2 className="size-4 animate-spin" strokeWidth={1.75} />
                                            Loading…
                                        </div>
                                    </td>
                                </tr>
                            )}
                            {!loading && workspaces?.data.length === 0 && (
                                <tr>
                                    <td colSpan={5} className="px-4 py-6 text-center text-muted-foreground-1">
                                        No workspaces yet.
                                    </td>
                                </tr>
                            )}
                            {!loading &&
                                workspaces?.data.map((workspace) => (
                                    <tr key={workspace.id}>
                                        <td className="px-4 py-3 font-medium text-foreground">{workspace.name}</td>
                                        <td className="px-4 py-3 text-muted-foreground-1">{workspace.description || '—'}</td>
                                        <td className="px-4 py-3 capitalize text-muted-foreground-1">{workspace.pivot.role}</td>
                                        <td className="px-4 py-3 text-muted-foreground-1">
                                            {new Date(workspace.created_at).toLocaleDateString()}
                                        </td>
                                        <td className="px-4 py-3">
                                            <div className="flex justify-end gap-1">
                                                <ActionButton
                                                    icon={<LayoutDashboard className="size-4" strokeWidth={1.75} />}
                                                    label="Dashboard"
                                                    ariaLabel={`View ${workspace.name} dashboard`}
                                                    onClick={() => navigate(`/workspaces/${workspace.id}/dashboard`)}
                                                    hoverClassName="hover:text-primary"
                                                />
                                                {can(workspace.id, 'workspace.update') && (
                                                    <ActionButton
                                                        icon={<Pencil className="size-4" strokeWidth={1.75} />}
                                                        label="Edit"
                                                        ariaLabel={`Edit ${workspace.name}`}
                                                        onClick={() => navigate(`/workspaces/${workspace.id}/edit`)}
                                                        hoverClassName="hover:text-primary"
                                                    />
                                                )}
                                                {can(workspace.id, 'workspace.delete') && (
                                                    <ActionButton
                                                        icon={<Trash2 className="size-4" strokeWidth={1.75} />}
                                                        label="Delete"
                                                        ariaLabel={`Delete ${workspace.name}`}
                                                        onClick={() => openDeleteDialog(workspace)}
                                                        hoverClassName="hover:text-destructive"
                                                    />
                                                )}
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                        </tbody>
                    </table>
                </div>
            </Card>

            {workspaces && workspaces.last_page > 1 && (
                <div className="mt-4 flex items-center justify-between text-sm">
                    <p className="text-muted-foreground-1">
                        Page {workspaces.current_page} of {workspaces.last_page} ({workspaces.total} total)
                    </p>
                    <div className="flex gap-2">
                        <Button variant="secondary" disabled={page <= 1} onClick={() => setPage((p) => p - 1)}>
                            Previous
                        </Button>
                        <Button variant="secondary" disabled={page >= workspaces.last_page} onClick={() => setPage((p) => p + 1)}>
                            Next
                        </Button>
                    </div>
                </div>
            )}

            <ConfirmDialog
                id="confirm-delete-workspace"
                title="Delete workspace"
                description={`Delete "${deleteTarget?.name}"? This also deletes its videos. This cannot be undone.`}
                confirmLabel={deleting ? 'Deleting…' : 'Delete'}
                confirmIcon={<Trash2 className="size-4" strokeWidth={1.75} />}
                confirmDisabled={deleting}
                variant="destructive"
                onConfirm={confirmDelete}
            />
        </AppLayout>
    );
}
