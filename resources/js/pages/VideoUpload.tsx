import { ChangeEvent, DragEvent, FormEvent, useEffect, useRef, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { AppLayout } from '@/components/AppLayout';
import { api } from '../lib/api';
import { cn } from '../lib/utils';
import { FileVideo, Upload, X } from 'lucide-react';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';

const MAX_FILE_SIZE = 2 * 1024 * 1024 * 1024; // 2 GB

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
    const [error, setError] = useState<string | null>(null);
    const [submitting, setSubmitting] = useState(false);
    const [submitted, setSubmitted] = useState(false);
    const inputRef = useRef<HTMLInputElement>(null);

    useEffect(() => {
        api.get<{ data: WorkspaceOption[] }>('/api/workspaces', { params: { per_page: 100 } })
            .then((res) => setWorkspaces(res.data.data))
            .catch(() => setWorkspaces([]));
    }, []);

    function selectFile(selected: File | null) {
        setSubmitted(false);
        setError(null);

        if (!selected) {
            setFile(null);
            return;
        }
        if (!selected.type.startsWith('video/')) {
            setError('Please choose a video file.');
            return;
        }
        if (selected.size > MAX_FILE_SIZE) {
            setError('That file is larger than the 2 GB limit.');
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

    function handleSubmit(e: FormEvent) {
        e.preventDefault();
        // UI only for now — wire this up to the upload endpoint once it exists.
        setSubmitting(true);
        setTimeout(() => {
            setSubmitting(false);
            setSubmitted(true);
        }, 600);
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
                    <form onSubmit={handleSubmit} className="flex flex-col gap-4">
                        {error && (
                            <Alert variant="destructive">
                                <AlertDescription>{error}</AlertDescription>
                            </Alert>
                        )}

                        {submitted && (
                            <Alert>
                                <AlertDescription>Looks good — this form isn't wired up to an upload endpoint yet.</AlertDescription>
                            </Alert>
                        )}

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
                                accept="video/*"
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
                                    <p className="text-xs text-muted-foreground-1">MP4, MOV, WebM up to 2 GB</p>
                                </>
                            )}
                        </div>

                        <div className="flex flex-col gap-1.5">
                            <Label htmlFor="video-workspace">Workspace</Label>
                            <select
                                id="video-workspace"
                                value={workspaceId}
                                onChange={(e) => setWorkspaceId(e.target.value)}
                                required
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
                            <Input id="video-title" value={title} onChange={(e) => setTitle(e.target.value)} required />
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
                            <Button type="submit" disabled={!file || !workspaceId || !title || submitting}>
                                {submitting ? 'Uploading…' : (
                                    <>
                                        Upload
                                        <Upload className="size-4" strokeWidth={1.75} />
                                    </>
                                )}
                            </Button>
                            <Button type="button" variant="secondary" onClick={() => navigate('/videos')}>
                                Cancel
                            </Button>
                        </div>
                    </form>
                </CardContent>
            </Card>
        </AppLayout>
    );
}
