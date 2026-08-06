import { useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { AppLayout } from '@/components/AppLayout';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import { HSOverlay } from 'preline';
import { api } from '../lib/api';
import { getErrorMessages } from '../lib/errors';
import { cn } from '../lib/utils';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { ConfirmDialog } from '@/components/ui/confirm-dialog';

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
    const [workspaces, setWorkspaces] = useState<PaginatedWorkspaces | null>(null);
    const [page, setPage] = useState(1);
    const [sort, setSort] = useState<SortField>('created_at');
    const [direction, setDirection] = useState<'asc' | 'desc'>('desc');
    const [loading, setLoading] = useState(true);
    const [listError, setListError] = useState<string[]>([]);

    const [deleteTarget, setDeleteTarget] = useState<Workspace | null>(null);
    const [deleting, setDeleting] = useState(false);

    function load() {
        setLoading(true);
        api.get<PaginatedWorkspaces>('/api/workspaces', { params: { page, sort, direction, per_page: 10 } })
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
            load();
        } catch (err) {
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

            {listError.length > 0 && (
                <Alert variant="destructive" className="mt-4">
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
                                        Loading…
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
                                                {(workspace.pivot.role === 'owner' || workspace.pivot.role === 'admin') && (
                                                    <button
                                                        type="button"
                                                        onClick={() => navigate(`/workspaces/${workspace.id}/edit`)}
                                                        aria-label={`Edit ${workspace.name}`}
                                                        title="Edit"
                                                        className="flex size-8 items-center justify-center rounded-lg text-muted-foreground-1 hover:bg-layer-hover hover:text-primary"
                                                    >
                                                        <Pencil className="size-4" strokeWidth={1.75} />
                                                    </button>
                                                )}
                                                {workspace.pivot.role === 'owner' && (
                                                    <button
                                                        type="button"
                                                        onClick={() => openDeleteDialog(workspace)}
                                                        aria-label={`Delete ${workspace.name}`}
                                                        title="Delete"
                                                        className="flex size-8 items-center justify-center rounded-lg text-muted-foreground-1 hover:bg-layer-hover hover:text-destructive"
                                                    >
                                                        <Trash2 className="size-4" strokeWidth={1.75} />
                                                    </button>
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
