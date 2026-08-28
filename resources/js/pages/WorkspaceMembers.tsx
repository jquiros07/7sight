import { FormEvent, useEffect, useState } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import { AppLayout } from '@/components/AppLayout';
import { api } from '../lib/api';
import { useAuth } from '../lib/auth';
import { getErrorMessages } from '../lib/errors';
import { ChevronLeft, Loader2, Mail, Trash2, UserPlus } from 'lucide-react';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { ActionButton } from '@/components/ui/action-button';

type Member = {
    id: number;
    name: string;
    email: string;
    role: string;
    is_owner: boolean;
};

type Invitation = {
    id: number;
    email: string;
    role: string;
    expires_at: string;
    invited_at: string;
};

type MembersData = {
    members: Member[];
    invitations: Invitation[];
};

export default function WorkspaceMembers() {
    const { id } = useParams<{ id: string }>();
    const workspaceId = Number(id);
    const navigate = useNavigate();
    const { can } = useAuth();

    const [data, setData] = useState<MembersData | null>(null);
    const [loading, setLoading] = useState(true);
    const [loadError, setLoadError] = useState<string[]>([]);

    const [inviteEmail, setInviteEmail] = useState('');
    const [inviteRole, setInviteRole] = useState('member');
    const [inviting, setInviting] = useState(false);
    const [inviteError, setInviteError] = useState<string[]>([]);
    const [statusMessage, setStatusMessage] = useState<string | null>(null);

    const [savingMemberId, setSavingMemberId] = useState<number | null>(null);
    const [removingMemberId, setRemovingMemberId] = useState<number | null>(null);
    const [cancellingInvitationId, setCancellingInvitationId] = useState<number | null>(null);

    const canManage = can(workspaceId, 'workspace.update');

    function load() {
        setLoading(true);
        api.get<MembersData>(`/api/workspaces/${id}/members`)
            .then((res) => setData(res.data))
            .catch((err) => setLoadError(getErrorMessages(err)))
            .finally(() => setLoading(false));
    }

    useEffect(load, [id]);

    async function handleInvite(e: FormEvent) {
        e.preventDefault();
        setInviteError([]);
        setStatusMessage(null);
        setInviting(true);
        try {
            const res = await api.post<{ status: 'added' | 'invited'; email: string }>(`/api/workspaces/${id}/members`, {
                email: inviteEmail,
                role: inviteRole,
            });
            setStatusMessage(
                res.data.status === 'added'
                    ? `${res.data.email} was added to this workspace.`
                    : `An invite was sent to ${res.data.email}.`,
            );
            setInviteEmail('');
            load();
        } catch (err) {
            setInviteError(getErrorMessages(err));
        } finally {
            setInviting(false);
        }
    }

    async function handleRoleChange(member: Member, role: string) {
        setSavingMemberId(member.id);
        setLoadError([]);
        try {
            await api.patch(`/api/workspaces/${id}/members/${member.id}`, { role });
            load();
        } catch (err) {
            setLoadError(getErrorMessages(err));
        } finally {
            setSavingMemberId(null);
        }
    }

    async function handleRemove(member: Member) {
        setRemovingMemberId(member.id);
        setLoadError([]);
        try {
            await api.delete(`/api/workspaces/${id}/members/${member.id}`);
            load();
        } catch (err) {
            setLoadError(getErrorMessages(err));
        } finally {
            setRemovingMemberId(null);
        }
    }

    async function handleCancelInvitation(invitation: Invitation) {
        setCancellingInvitationId(invitation.id);
        setLoadError([]);
        try {
            await api.delete(`/api/workspaces/${id}/invitations/${invitation.id}`);
            load();
        } catch (err) {
            setLoadError(getErrorMessages(err));
        } finally {
            setCancellingInvitationId(null);
        }
    }

    return (
        <AppLayout active="workspaces">
            <Button variant="secondary" onClick={() => navigate('/workspaces')}>
                <ChevronLeft className="size-4" strokeWidth={1.75} />
                Back to workspaces
            </Button>

            <h1 className="mt-4 font-heading text-2xl font-medium">Members</h1>

            {statusMessage && (
                <Alert variant="success" className="mt-4" onDismiss={() => setStatusMessage(null)}>
                    <AlertDescription>{statusMessage}</AlertDescription>
                </Alert>
            )}

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

            {canManage && (
                <Card className="mt-6 max-w-lg">
                    <CardHeader>
                        <CardTitle>Invite someone</CardTitle>
                        <CardDescription>
                            If they already have an account, they're added right away. Otherwise we'll email them an
                            invite to create one.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={handleInvite} noValidate className="flex flex-col gap-4">
                            {inviteError.length > 0 && (
                                <Alert variant="destructive" onDismiss={() => setInviteError([])}>
                                    <AlertDescription>
                                        <ul className="list-disc space-y-1 pl-4">
                                            {inviteError.map((message) => (
                                                <li key={message}>{message}</li>
                                            ))}
                                        </ul>
                                    </AlertDescription>
                                </Alert>
                            )}
                            <div className="flex flex-col gap-1.5">
                                <Label htmlFor="invite-email">Email</Label>
                                <Input
                                    id="invite-email"
                                    type="email"
                                    required
                                    value={inviteEmail}
                                    onChange={(e) => setInviteEmail(e.target.value)}
                                />
                            </div>
                            <div className="flex flex-col gap-1.5">
                                <Label htmlFor="invite-role">Role</Label>
                                <select
                                    id="invite-role"
                                    value={inviteRole}
                                    onChange={(e) => setInviteRole(e.target.value)}
                                    className="block w-full rounded-lg border-layer-line bg-layer py-1.5 pl-3 pr-8 text-sm text-foreground focus:border-primary-focus focus:ring-primary-focus"
                                >
                                    <option value="member">Member</option>
                                    <option value="admin">Admin</option>
                                </select>
                            </div>
                            <div>
                                <Button type="submit" disabled={inviting}>
                                    {inviting ? (
                                        'Sending…'
                                    ) : (
                                        <>
                                            Invite
                                            <UserPlus className="size-4" strokeWidth={1.75} />
                                        </>
                                    )}
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            )}

            {loading && (
                <div className="mt-10 flex flex-col items-center gap-2 text-center">
                    <Loader2 className="size-6 animate-spin text-primary" strokeWidth={1.75} />
                    <p className="text-sm text-muted-foreground-1">Loading…</p>
                </div>
            )}

            {!loading && data && (
                <>
                    <Card className="mt-6">
                        <CardHeader>
                            <CardTitle>Current members</CardTitle>
                        </CardHeader>
                        <CardContent className="pt-0">
                            <div className="overflow-x-auto">
                                <table className="min-w-full divide-y divide-card-line text-sm">
                                    <thead>
                                        <tr className="text-left text-muted-foreground-1">
                                            <th className="px-4 py-2 font-medium">Name</th>
                                            <th className="px-4 py-2 font-medium">Email</th>
                                            <th className="px-4 py-2 font-medium">Role</th>
                                            <th className="px-4 py-2" />
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-card-line">
                                        {data.members.map((member) => (
                                            <tr key={member.id}>
                                                <td className="px-4 py-3 font-medium text-foreground">{member.name}</td>
                                                <td className="px-4 py-3 text-muted-foreground-1">{member.email}</td>
                                                <td className="px-4 py-3">
                                                    {canManage && !member.is_owner ? (
                                                        <select
                                                            value={member.role}
                                                            disabled={savingMemberId === member.id}
                                                            onChange={(e) => handleRoleChange(member, e.target.value)}
                                                            className="block rounded-lg border-layer-line bg-layer py-1 pl-2 pr-8 text-sm text-foreground focus:border-primary-focus focus:ring-primary-focus"
                                                        >
                                                            <option value="member">Member</option>
                                                            <option value="admin">Admin</option>
                                                        </select>
                                                    ) : (
                                                        <span className="capitalize text-muted-foreground-1">{member.role}</span>
                                                    )}
                                                </td>
                                                <td className="px-4 py-3">
                                                    {canManage && !member.is_owner && (
                                                        <div className="flex justify-end">
                                                            <ActionButton
                                                                icon={<Trash2 className="size-4" strokeWidth={1.75} />}
                                                                label="Remove"
                                                                ariaLabel={`Remove ${member.name}`}
                                                                disabled={removingMemberId === member.id}
                                                                onClick={() => handleRemove(member)}
                                                                hoverClassName="hover:text-destructive"
                                                            />
                                                        </div>
                                                    )}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </CardContent>
                    </Card>

                    {canManage && (
                        <Card className="mt-4">
                            <CardHeader>
                                <CardTitle>Pending invitations</CardTitle>
                            </CardHeader>
                            <CardContent className="pt-0">
                                {data.invitations.length === 0 ? (
                                    <p className="py-6 text-center text-sm text-muted-foreground-1">No pending invitations.</p>
                                ) : (
                                    <div className="overflow-x-auto">
                                        <table className="min-w-full divide-y divide-card-line text-sm">
                                            <thead>
                                                <tr className="text-left text-muted-foreground-1">
                                                    <th className="px-4 py-2 font-medium">Email</th>
                                                    <th className="px-4 py-2 font-medium">Role</th>
                                                    <th className="px-4 py-2 font-medium">Expires</th>
                                                    <th className="px-4 py-2" />
                                                </tr>
                                            </thead>
                                            <tbody className="divide-y divide-card-line">
                                                {data.invitations.map((invitation) => (
                                                    <tr key={invitation.id}>
                                                        <td className="px-4 py-3 font-medium text-foreground">
                                                            <span className="inline-flex items-center gap-1.5">
                                                                <Mail className="size-3.5 text-muted-foreground-2" strokeWidth={1.75} />
                                                                {invitation.email}
                                                            </span>
                                                        </td>
                                                        <td className="px-4 py-3 capitalize text-muted-foreground-1">{invitation.role}</td>
                                                        <td className="px-4 py-3 text-muted-foreground-1">
                                                            {new Date(invitation.expires_at).toLocaleDateString()}
                                                        </td>
                                                        <td className="px-4 py-3">
                                                            <div className="flex justify-end">
                                                                <ActionButton
                                                                    icon={<Trash2 className="size-4" strokeWidth={1.75} />}
                                                                    label="Cancel invitation"
                                                                    ariaLabel={`Cancel invitation for ${invitation.email}`}
                                                                    disabled={cancellingInvitationId === invitation.id}
                                                                    onClick={() => handleCancelInvitation(invitation)}
                                                                    hoverClassName="hover:text-destructive"
                                                                />
                                                            </div>
                                                        </td>
                                                    </tr>
                                                ))}
                                            </tbody>
                                        </table>
                                    </div>
                                )}
                            </CardContent>
                        </Card>
                    )}
                </>
            )}
        </AppLayout>
    );
}
