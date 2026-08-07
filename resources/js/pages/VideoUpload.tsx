import { ChangeEvent, DragEvent, FormEvent, useEffect, useRef, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import axios from 'axios';
import { AppLayout } from '@/components/AppLayout';
import { api } from '../lib/api';
import { getErrorMessages } from '../lib/errors';
import { cn } from '../lib/utils';
import { FileVideo, Upload, X } from 'lucide-react';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Progress } from '@/components/ui/progress';
import { Textarea } from '@/components/ui/textarea';

type WorkspaceOption = { id: number; name: string };

function formatFileSize(bytes: number): string {
    if (bytes === 0) return '0 B';
    const units = ['B', 'KB', 'MB', 'GB'];
    const i = Math.min(Math.floor(Math.log(bytes) / Math.log(1024)), units.length - 1);
    return `${(bytes / 1024 ** i).toFixed(i === 0 ? 0 : 1)} ${units[i]}`;
}

function titleFromFilename(filename: string): string {
    return filename.replace(/\.[^/.]+$/, '');
}

export default function VideoUpload() {
    const navigate = useNavigate();

    const [workspaces, setWorkspaces] = useState<WorkspaceOption[]>([]);
    const [workspaceId, setWorkspaceId] = useState('');
    const [file, setFile] = useState<File | null>(null);
    const [title, setTitle] = useState('');
    const [description, setDescription] = useState('');
    const [dragActive, setDragActive] = useState(false);
    const [submitting, setSubmitting] = useState(false);
    const [submitted, setSubmitted] = useState(false);
    const [formErrors, setFormErrors] = useState<string[]>([]);
    const [uploadProgress, setUploadProgress] = useState<number | null>(null);
    const inputRef = useRef<HTMLInputElement>(null);
    const abortControllerRef = useRef<AbortController | null>(null);

    useEffect(() => {
        api.get<{ data: WorkspaceOption[] }>('/api/workspaces', { params: { per_page: 100 } })
            .then((res) => setWorkspaces(res.data.data))
            .catch(() => setWorkspaces([]));
    }, []);

    function selectFile(selected: File | null) {
        setSubmitted(false);

        if (!selected) {
            setFile(null);
            return;
        }

        setFile(selected);
        setTitle((current) => current || titleFromFilename(selected.name));
    }

    function clearFile() {
        selectFile(null);
        if (inputRef.current) {
            inputRef.current.value = '';
        }
    }

    function handleDrop(e: DragEvent<HTMLDivElement>) {
        e.preventDefault();
        setDragActive(false);
        selectFile(e.dataTransfer.files?.[0] ?? null);
    }

    async function handleSubmit(e: FormEvent) {
        e.preventDefault();
        setFormErrors([]);
        setSubmitted(false);
        setSubmitting(true);
        setUploadProgress(0);

        const formData = new FormData();
        formData.append('workspace_id', workspaceId);
        formData.append('title', title);
        if (description) formData.append('description', description);
        if (file) formData.append('file', file);

        const controller = new AbortController();
        abortControllerRef.current = controller;

        try {
            await api.post('/api/videos', formData, {
                signal: controller.signal,
                onUploadProgress: (e) => {
                    if (e.total) {
                        setUploadProgress(Math.round((e.loaded / e.total) * 100));
                    }
                },
            });
            setFile(null);
            if (inputRef.current) {
                inputRef.current.value = '';
            }
            setTitle('');
            setDescription('');
            setWorkspaceId('');
            setSubmitted(true);
        } catch (err) {
            if (!axios.isCancel(err)) {
                setFormErrors(getErrorMessages(err));
            }
        } finally {
            setSubmitting(false);
            setUploadProgress(null);
            abortControllerRef.current = null;
        }
    }

    function cancelUpload() {
        abortControllerRef.current?.abort();
    }

    return (
        <AppLayout active="videos">
            <h1 className="font-heading text-2xl font-medium">New video</h1>

            <Card className="mt-6 max-w-xl">
                <CardHeader>
                    <CardTitle>New video</CardTitle>
                    <CardDescription>Upload a video to a workspace for analysis.</CardDescription>
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

                        {submitted && (
                            <Alert variant="success" onDismiss={() => setSubmitted(false)}>
                                <AlertDescription>Video uploaded.</AlertDescription>
                            </Alert>
                        )}

                        {submitting && file && uploadProgress !== null ? (
                            <div className="rounded-xl border border-layer-line p-4">
                                <div className="mb-2 flex items-center justify-between gap-x-3">
                                    <div className="flex min-w-0 items-center gap-x-3">
                                        <span className="flex size-8 shrink-0 items-center justify-center rounded-lg border border-layer-line bg-layer text-primary">
                                            <FileVideo className="size-4" strokeWidth={1.75} />
                                        </span>
                                        <div className="min-w-0">
                                            <p className="truncate text-sm font-medium text-foreground">{file.name}</p>
                                            <p className="text-xs text-muted-foreground-1">{formatFileSize(file.size)}</p>
                                        </div>
                                    </div>
                                    <button
                                        type="button"
                                        onClick={cancelUpload}
                                        aria-label="Cancel upload"
                                        title="Cancel upload"
                                        className="flex shrink-0 items-center text-muted-foreground-1 hover:text-destructive focus:outline-hidden"
                                    >
                                        <X className="size-4" strokeWidth={1.75} />
                                    </button>
                                </div>
                                <div className="flex items-center gap-x-3 whitespace-nowrap">
                                    <Progress value={uploadProgress} />
                                    <div className="w-10 shrink-0 text-end">
                                        <span className="text-sm text-foreground">{uploadProgress}%</span>
                                    </div>
                                </div>
                            </div>
                        ) : (
                            <div
                                onDragOver={(e) => {
                                    e.preventDefault();
                                    setDragActive(true);
                                }}
                                onDragLeave={() => setDragActive(false)}
                                onDrop={handleDrop}
                                onClick={() => inputRef.current?.click()}
                                className={cn(
                                    'flex cursor-pointer flex-col items-center gap-2 rounded-xl border-2 border-dashed px-6 py-10 text-center transition-colors',
                                    dragActive ? 'border-primary bg-primary/5' : 'border-layer-line hover:border-primary/50',
                                )}
                            >
                                <input
                                    ref={inputRef}
                                    type="file"
                                    accept="video/mp4,video/quicktime,video/x-msvideo,video/x-matroska,video/webm"
                                    className="hidden"
                                    onChange={(e: ChangeEvent<HTMLInputElement>) => selectFile(e.target.files?.[0] ?? null)}
                                />
                                {file ? (
                                    <>
                                        <FileVideo className="size-8 text-primary" strokeWidth={1.5} />
                                        <p className="max-w-full truncate text-sm font-medium text-foreground">{file.name}</p>
                                        <p className="text-xs text-muted-foreground-1">{formatFileSize(file.size)}</p>
                                        <button
                                            type="button"
                                            onClick={(e) => {
                                                e.stopPropagation();
                                                clearFile();
                                            }}
                                            className="mt-1 flex items-center gap-1 text-xs font-medium text-muted-foreground-1 hover:text-destructive"
                                        >
                                            <X className="size-3.5" strokeWidth={1.75} />
                                            Remove
                                        </button>
                                    </>
                                ) : (
                                    <>
                                        <Upload className="size-8 text-muted-foreground-1" strokeWidth={1.5} />
                                        <p className="text-sm text-foreground">
                                            <span className="font-medium text-primary">Click to upload</span> or drag and drop
                                        </p>
                                        <p className="text-xs text-muted-foreground-1">
                                            MP4, MOV, AVI, MKV, WebM &middot; up to 500 MB &middot; 15 min &middot; 1080p
                                        </p>
                                    </>
                                )}
                            </div>
                        )}

                        <div className="flex flex-col gap-1.5">
                            <Label htmlFor="video-workspace">Workspace</Label>
                            <select
                                id="video-workspace"
                                value={workspaceId}
                                onChange={(e) => setWorkspaceId(e.target.value)}
                                className="block w-full rounded-lg border-layer-line bg-layer px-4 py-2.5 text-sm text-foreground focus:border-primary-focus focus:ring-primary-focus sm:py-3"
                            >
                                <option value="" disabled>
                                    Select a workspace
                                </option>
                                {workspaces.map((workspace) => (
                                    <option key={workspace.id} value={workspace.id}>
                                        {workspace.name}
                                    </option>
                                ))}
                            </select>
                        </div>

                        <div className="flex flex-col gap-1.5">
                            <Label htmlFor="video-title">Title</Label>
                            <Input id="video-title" value={title} onChange={(e) => setTitle(e.target.value)} />
                        </div>

                        <div className="flex flex-col gap-1.5">
                            <Label htmlFor="video-description">Description</Label>
                            <Textarea
                                id="video-description"
                                value={description}
                                onChange={(e) => setDescription(e.target.value)}
                                rows={3}
                            />
                        </div>

                        <div className="flex justify-center gap-2">
                            <Button type="submit" disabled={submitting}>
                                {submitting ? 'Uploading…' : (
                                    <>
                                        Upload
                                        <Upload className="size-4" strokeWidth={1.75} />
                                    </>
                                )}
                            </Button>
                            <Button type="button" variant="secondary" onClick={() => navigate('/videos')} disabled={submitting}>
                                Cancel
                            </Button>
                        </div>
                    </form>
                </CardContent>
            </Card>
        </AppLayout>
    );
}
