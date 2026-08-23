import { FormEvent, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { AppLayout } from '@/components/AppLayout';
import { api } from '../lib/api';
import { getErrorMessages } from '../lib/errors';
import { Camera as CameraIcon } from 'lucide-react';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

export default function CameraCreate() {
    const navigate = useNavigate();

    const [name, setName] = useState('');
    const [location, setLocation] = useState('');
    const [streamUrl, setStreamUrl] = useState('');
    const [formErrors, setFormErrors] = useState<string[]>([]);
    const [submitting, setSubmitting] = useState(false);

    async function handleSubmit(e: FormEvent) {
        e.preventDefault();
        setFormErrors([]);
        setSubmitting(true);
        try {
            await api.post('/api/cameras', { name, location: location || null, stream_url: streamUrl });
            navigate('/cameras', { state: { message: 'Camera added.' } });
        } catch (err) {
            setFormErrors(getErrorMessages(err));
        } finally {
            setSubmitting(false);
        }
    }

    return (
        <AppLayout active="cameras">
            <h1 className="font-heading text-2xl font-medium">New camera</h1>

            <Card className="mt-6 max-w-md">
                <CardHeader>
                    <CardTitle>Camera details</CardTitle>
                    <CardDescription>Give it a name and its RTSP stream URL.</CardDescription>
                </CardHeader>
                <CardContent>
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
                            <Label htmlFor="camera-name">Name</Label>
                            <Input id="camera-name" value={name} onChange={(e) => setName(e.target.value)} />
                        </div>
                        <div className="flex flex-col gap-1.5">
                            <Label htmlFor="camera-location">Location</Label>
                            <Input id="camera-location" value={location} onChange={(e) => setLocation(e.target.value)} />
                        </div>
                        <div className="flex flex-col gap-1.5">
                            <Label htmlFor="camera-stream-url">Stream URL</Label>
                            <Input
                                id="camera-stream-url"
                                placeholder="rtsp://user:pass@192.168.1.10:554/stream1"
                                value={streamUrl}
                                onChange={(e) => setStreamUrl(e.target.value)}
                            />
                        </div>
                        <div className="flex justify-center gap-2">
                            <Button type="submit" disabled={submitting}>
                                {submitting ? 'Connecting…' : (
                                    <>
                                        Add camera
                                        <CameraIcon className="size-4" strokeWidth={1.75} />
                                    </>
                                )}
                            </Button>
                            <Button type="button" variant="secondary" onClick={() => navigate('/cameras')}>
                                Cancel
                            </Button>
                        </div>
                    </form>
                </CardContent>
            </Card>
        </AppLayout>
    );
}
