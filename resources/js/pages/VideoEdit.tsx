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
import { Textarea } from '@/components/ui/textarea';

type Video = {
    id: number;
    title: string;
    description: string | null;
};

export default function VideoEdit() {
    const { id } = useParams<{ id: string }>();
    const navigate = useNavigate();

    const [title, setTitle] = useState('');
    const [description, setDescription] = useState('');
    const [loading, setLoading] = useState(true);
    const [loadError, setLoadError] = useState<string[]>([]);
    const [formErrors, setFormErrors] = useState<string[]>([]);
    const [submitting, setSubmitting] = useState(false);

    useEffect(() => {
        api.get<Video>(`/api/videos/${id}`)
            .then((res) => {
                setTitle(res.data.title);
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
            await api.patch(`/api/videos/${id}`, { title, description: description || null });
            navigate('/videos', { state: { message: 'Video updated.' } });
        } catch (err) {
            setFormErrors(getErrorMessages(err));
        } finally {
            setSubmitting(false);
        }
    }

    return (
        <AppLayout active="videos">
            <h1 className="font-heading text-2xl font-medium">Edit video</h1>

            <Card className="mt-6 max-w-md">
                <CardHeader>
                    <CardTitle>Video details</CardTitle>
                    <CardDescription>Update the title and description.</CardDescription>
                </CardHeader>
                <CardContent>
                    {loadError.length > 0 && (
                        <Alert variant="destructive" onDismiss={() => setLoadError([])}>
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
                            <form onSubmit={handleSubmit} noValidate className="flex flex-col gap-4">
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
                                    <Label htmlFor="video-edit-title">Title</Label>
                                    <Input id="video-edit-title" value={title} onChange={(e) => setTitle(e.target.value)} />
                                </div>
                                <div className="flex flex-col gap-1.5">
                                    <Label htmlFor="video-edit-description">Description</Label>
                                    <Textarea
                                        id="video-edit-description"
                                        value={description}
                                        onChange={(e) => setDescription(e.target.value)}
                                        rows={3}
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
                                    <Button type="button" variant="secondary" onClick={() => navigate('/videos')}>
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
