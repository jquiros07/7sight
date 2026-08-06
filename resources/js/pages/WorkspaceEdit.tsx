import { FormEvent, useEffect, useState } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import { AppLayout } from '@/components/AppLayout';
import { api } from '../lib/api';
import { getErrorMessages } from '../lib/errors';
import { Save } from 'lucide-react';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Workspace = {
    id: number;
    name: string;
    description: string | null;
};

export default function WorkspaceEdit() {
    const { id } = useParams<{ id: string }>();
    const navigate = useNavigate();

    const [name, setName] = useState('');
    const [description, setDescription] = useState('');
    const [loading, setLoading] = useState(true);
    const [loadError, setLoadError] = useState<string[]>([]);
    const [formErrors, setFormErrors] = useState<string[]>([]);
    const [submitting, setSubmitting] = useState(false);

    useEffect(() => {
        api.get<Workspace>(`/api/workspaces/${id}`)
            .then((res) => {
                setName(res.data.name);
                setDescription(res.data.description ?? '');
            })
            .catch((err) => setLoadError(getErrorMessages(err)))
            .finally(() => setLoading(false));
    }, [id]);

    async function handleSubmit(e: FormEvent) {
        e.preventDefault();
        setFormErrors([]);
        setSubmitting(true);
        try {
            await api.patch(`/api/workspaces/${id}`, { name, description: description || null });
            navigate('/workspaces');
        } catch (err) {
            setFormErrors(getErrorMessages(err));
        } finally {
            setSubmitting(false);
        }
    }

    return (
        <AppLayout active="workspaces">
            <h1 className="font-heading text-2xl font-medium">Edit workspace</h1>

            <Card className="mt-6 max-w-md">
                <CardHeader>
                    <CardTitle>Workspace details</CardTitle>
                    <CardDescription>Update the name and description.</CardDescription>
                </CardHeader>
                <CardContent>
                    {loadError.length > 0 && (
                        <Alert variant="destructive">
                            <AlertDescription>
                                <ul className="list-disc space-y-1 pl-4">
                                    {loadError.map((message) => (
                                        <li key={message}>{message}</li>
                                    ))}
                                </ul>
                            </AlertDescription>
                        </Alert>
                    )}
                    {loading ? (
                        <p className="text-sm text-muted-foreground-1">Loading…</p>
                    ) : (
                        loadError.length === 0 && (
                            <form onSubmit={handleSubmit} className="flex flex-col gap-4">
                                {formErrors.length > 0 && (
                                    <Alert variant="destructive">
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
                                    <Label htmlFor="ws-edit-name">Name</Label>
                                    <Input id="ws-edit-name" value={name} onChange={(e) => setName(e.target.value)} required />
                                </div>
                                <div className="flex flex-col gap-1.5">
                                    <Label htmlFor="ws-edit-description">Description</Label>
                                    <Input
                                        id="ws-edit-description"
                                        value={description}
                                        onChange={(e) => setDescription(e.target.value)}
                                    />
                                </div>
                                <div className="flex justify-center gap-2">
                                    <Button type="submit" disabled={submitting}>
                                        {submitting ? 'Saving…' : (
                                            <>
                                                Save
                                                <Save className="size-4" strokeWidth={1.75} />
                                            </>
                                        )}
                                    </Button>
                                    <Button type="button" variant="secondary" onClick={() => navigate('/workspaces')}>
                                        Cancel
                                    </Button>
                                </div>
                            </form>
                        )
                    )}
                </CardContent>
            </Card>
        </AppLayout>
    );
}