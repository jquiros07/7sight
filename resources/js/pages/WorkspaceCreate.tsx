import { FormEvent, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { AppLayout } from '@/components/AppLayout';
import { api } from '../lib/api';
import { getErrorMessages } from '../lib/errors';
import { FolderPlus } from 'lucide-react';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

export default function WorkspaceCreate() {
    const navigate = useNavigate();

    const [name, setName] = useState('');
    const [description, setDescription] = useState('');
    const [formErrors, setFormErrors] = useState<string[]>([]);
    const [submitting, setSubmitting] = useState(false);

    async function handleSubmit(e: FormEvent) {
        e.preventDefault();
        setFormErrors([]);
        setSubmitting(true);
        try {
            await api.post('/api/workspaces', { name, description: description || null });
            navigate('/workspaces');
        } catch (err) {
            setFormErrors(getErrorMessages(err));
        } finally {
            setSubmitting(false);
        }
    }

    return (
        <AppLayout active="workspaces">
            <h1 className="font-heading text-2xl font-medium">New workspace</h1>

            <Card className="mt-6 max-w-md">
                <CardHeader>
                    <CardTitle>Workspace details</CardTitle>
                    <CardDescription>Give it a name and an optional description.</CardDescription>
                </CardHeader>
                <CardContent>
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
                            <Label htmlFor="ws-name">Name</Label>
                            <Input id="ws-name" value={name} onChange={(e) => setName(e.target.value)} required />
                        </div>
                        <div className="flex flex-col gap-1.5">
                            <Label htmlFor="ws-description">Description</Label>
                            <Input id="ws-description" value={description} onChange={(e) => setDescription(e.target.value)} />
                        </div>
                        <div className="flex justify-center gap-2">
                            <Button type="submit" disabled={submitting}>
                                {submitting ? 'Creating…' : (
                                    <>
                                        Create
                                        <FolderPlus className="size-4" strokeWidth={1.75} />
                                    </>
                                )}
                            </Button>
                            <Button type="button" variant="secondary" onClick={() => navigate('/workspaces')}>
                                Cancel
                            </Button>
                        </div>
                    </form>
                </CardContent>
            </Card>
        </AppLayout>
    );
}