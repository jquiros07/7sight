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

type Camera = {
    id: number;
    name: string;
    location: string | null;
    stream_url: string;
};

export default function CameraEdit() {
    const { id } = useParams<{ id: string }>();
    const navigate = useNavigate();

    const [name, setName] = useState('');
    const [location, setLocation] = useState('');
    const [streamUrl, setStreamUrl] = useState('');
    const [loading, setLoading] = useState(true);
    const [loadError, setLoadError] = useState<string[]>([]);
    const [formErrors, setFormErrors] = useState<string[]>([]);
    const [submitting, setSubmitting] = useState(false);

    useEffect(() => {
        api.get<Camera>(`/api/cameras/${id}`)
            .then((res) => {
                setName(res.data.name);
                setLocation(res.data.location ?? '');
                setStreamUrl(res.data.stream_url);
            })
            .catch((err) => setLoadError(getErrorMessages(err)))
            .finally(() => setLoading(false));
    }, [id]);

    async function handleSubmit(e: FormEvent) {
        e.preventDefault();
        setFormErrors([]);
        setSubmitting(true);
        try {
            await api.patch(`/api/cameras/${id}`, { name, location: location || null, stream_url: streamUrl });
            navigate('/cameras', { state: { message: 'Camera updated.' } });
        } catch (err) {
            setFormErrors(getErrorMessages(err));
        } finally {
            setSubmitting(false);
        }
    }

    return (
        <AppLayout active="cameras">
            <h1 className="font-heading text-2xl font-medium">Edit camera</h1>

            <Card className="mt-6 max-w-md">
                <CardHeader>
                    <CardTitle>Camera details</CardTitle>
                    <CardDescription>Update the name, location, or stream URL.</CardDescription>
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
                                    <Label htmlFor="camera-edit-name">Name</Label>
                                    <Input id="camera-edit-name" value={name} onChange={(e) => setName(e.target.value)} />
                                </div>
                                <div className="flex flex-col gap-1.5">
                                    <Label htmlFor="camera-edit-location">Location</Label>
                                    <Input
                                        id="camera-edit-location"
                                        value={location}
                                        onChange={(e) => setLocation(e.target.value)}
                                    />
                                </div>
                                <div className="flex flex-col gap-1.5">
                                    <Label htmlFor="camera-edit-stream-url">Stream URL</Label>
                                    <Input
                                        id="camera-edit-stream-url"
                                        value={streamUrl}
                                        onChange={(e) => setStreamUrl(e.target.value)}
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
                                    <Button type="button" variant="secondary" onClick={() => navigate('/cameras')}>
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
