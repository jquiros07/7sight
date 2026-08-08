import { FormEvent, useEffect, useRef, useState } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import { AppLayout } from '@/components/AppLayout';
import { AnalysisSettingsFields } from '@/components/AnalysisSettingsFields';
import { ObjectDetectionMode, useAnalysisSettings } from '@/hooks/useAnalysisSettings';
import { api } from '../lib/api';
import { getErrorMessages } from '../lib/errors';
import { ChevronLeft, ChevronRight, Save } from 'lucide-react';
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
    analysis_types: string[] | null;
    auto_start_analysis: boolean | null;
    analysis_config: { object_detection?: { mode: ObjectDetectionMode; objects?: string[] } } | null;
};

const WIZARD_STEPS = [
    { index: 1, label: 'Video details' },
    { index: 2, label: 'Analysis settings' },
] as const;

export default function VideoEdit() {
    const { id } = useParams<{ id: string }>();
    const navigate = useNavigate();

    const [title, setTitle] = useState('');
    const [description, setDescription] = useState('');
    const [loading, setLoading] = useState(true);
    const [loadError, setLoadError] = useState<string[]>([]);
    const [formErrors, setFormErrors] = useState<string[]>([]);
    const [submitting, setSubmitting] = useState(false);
    const backBtnRef = useRef<HTMLButtonElement>(null);

    const analysisSettings = useAnalysisSettings();

    useEffect(() => {
        api.get<Video>(`/api/videos/${id}`)
            .then((res) => {
                setTitle(res.data.title);
                setDescription(res.data.description ?? '');
                analysisSettings.hydrate(res.data);
            })
            .catch((err) => setLoadError(getErrorMessages(err)))
            .finally(() => setLoading(false));
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [id]);

    // The form (and its Preline stepper/select) doesn't exist in the DOM until
    // loading finishes, so re-init and sync the select's visual state here.
    useEffect(() => {
        if (!loading && loadError.length === 0) {
            window.HSStaticMethods.autoInit();
            analysisSettings.syncSelectVisual();
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [loading, loadError]);

    async function handleSubmit(e: FormEvent) {
        e.preventDefault();
        setFormErrors([]);
        setSubmitting(true);
        try {
            await api.patch(`/api/videos/${id}`, {
                title,
                description: description || null,
                ...analysisSettings.toPayload(),
            });
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

            <Card className="mt-6 max-w-xl">
                <CardHeader>
                    <CardTitle>Video details</CardTitle>
                    <CardDescription>Update the video's details and analysis settings.</CardDescription>
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
                            <form onSubmit={handleSubmit} noValidate data-hs-stepper="">
                                {formErrors.length > 0 && (
                                    <Alert variant="destructive" className="mb-4" onDismiss={() => setFormErrors([])}>
                                        <AlertDescription>
                                            <ul className="list-disc space-y-1 pl-4">
                                                {formErrors.map((message) => (
                                                    <li key={message}>{message}</li>
                                                ))}
                                            </ul>
                                        </AlertDescription>
                                    </Alert>
                                )}

                                <ul className="mb-6 flex items-center">
                                    {WIZARD_STEPS.map((step, i) => (
                                        <li
                                            key={step.index}
                                            className="group flex flex-1 shrink basis-0 items-center gap-x-2 last:flex-none"
                                            data-hs-stepper-nav-item={JSON.stringify({ index: step.index })}
                                        >
                                            <span className="flex size-7 shrink-0 items-center justify-center rounded-full bg-layer text-sm font-medium text-muted-foreground-1 hs-stepper-active:bg-primary hs-stepper-active:text-primary-foreground hs-stepper-success:bg-primary hs-stepper-success:text-primary-foreground">
                                                {step.index}
                                            </span>
                                            <span className="text-sm font-medium text-muted-foreground-1 hs-stepper-active:text-foreground hs-stepper-success:text-foreground">
                                                {step.label}
                                            </span>
                                            {i < WIZARD_STEPS.length - 1 && <div className="h-px flex-1 bg-layer-line" />}
                                        </li>
                                    ))}
                                </ul>

                                <div data-hs-stepper-content-item={JSON.stringify({ index: 1 })}>
                                    <div className="flex flex-col gap-4">
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
                                            <Button type="button" variant="secondary" onClick={() => navigate('/videos')}>
                                                Cancel
                                            </Button>
                                            <Button type="button" data-hs-stepper-next-btn="">
                                                Next
                                                <ChevronRight className="size-4" strokeWidth={1.75} />
                                            </Button>
                                        </div>
                                    </div>
                                </div>

                                <div data-hs-stepper-content-item={JSON.stringify({ index: 2 })}>
                                    <div className="flex flex-col gap-4">
                                        <AnalysisSettingsFields {...analysisSettings} />

                                        <div className="flex justify-center gap-2">
                                            <Button ref={backBtnRef} type="button" variant="secondary" data-hs-stepper-back-btn="">
                                                <ChevronLeft className="size-4" strokeWidth={1.75} />
                                                Back
                                            </Button>
                                            <Button type="submit" disabled={submitting}>
                                                {submitting ? 'Saving…' : (
                                                    <>
                                                        Save
                                                        <Save className="size-4" strokeWidth={1.75} />
                                                    </>
                                                )}
                                            </Button>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        )
                    )}
                </CardContent>
            </Card>
        </AppLayout>
    );
}
