import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { AppLayout } from '@/components/AppLayout';
import { Plus, Sparkles } from 'lucide-react';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';

type ReadyVideo = {
    id: number;
    title: string;
    workspace: string;
    uploadedAt: string;
    duration: string;
    size: string;
};

// Sample data — the videos list isn't wired to a backend yet.
const READY_VIDEOS: ReadyVideo[] = [
    { id: 1, title: 'Product demo walkthrough', workspace: 'Marketing', uploadedAt: '2026-08-05T14:22:00Z', duration: '4:12', size: '182 MB' },
    { id: 2, title: 'Customer interview – Q3', workspace: 'Research', uploadedAt: '2026-08-05T09:10:00Z', duration: '18:47', size: '640 MB' },
    { id: 3, title: 'Warehouse safety walkthrough', workspace: 'Operations', uploadedAt: '2026-08-04T17:35:00Z', duration: '7:03', size: '295 MB' },
    { id: 4, title: 'Onboarding session recording', workspace: 'HR', uploadedAt: '2026-08-04T11:02:00Z', duration: '32:18', size: '1.1 GB' },
];

function ReadyBadge() {
    return (
        <span className="inline-flex items-center gap-1.5 rounded-full bg-primary/10 px-2.5 py-1 text-xs font-medium text-primary">
            <span className="size-1.5 rounded-full bg-primary" />
            Ready to analyze
        </span>
    );
}

export default function Videos() {
    const navigate = useNavigate();

    const [queuedIds, setQueuedIds] = useState<number[]>([]);
    const [showAnalyzeNote, setShowAnalyzeNote] = useState(false);

    function handleAnalyze(id: number) {
        // UI only for now — wire this up to the analysis pipeline once it exists.
        setQueuedIds((current) => [...current, id]);
        setShowAnalyzeNote(true);
    }

    return (
        <AppLayout active="videos">
            <div className="flex items-center justify-between">
                <h1 className="font-heading text-2xl font-medium">Videos</h1>
                <Button onClick={() => navigate('/videos/upload')}>
                    Upload
                    <Plus className="size-4" strokeWidth={1.75} />
                </Button>
            </div>

            {showAnalyzeNote && (
                <Alert className="mt-4">
                    <AlertDescription>Queued for analysis — this isn't wired up to the analysis pipeline yet.</AlertDescription>
                </Alert>
            )}

            <Card className="mt-4 overflow-hidden">
                <div className="overflow-x-auto">
                    <table className="w-full text-left text-sm">
                        <thead className="border-b border-card-line bg-surface/50">
                            <tr>
                                <th className="px-4 py-3">Title</th>
                                <th className="px-4 py-3">Workspace</th>
                                <th className="px-4 py-3">Status</th>
                                <th className="px-4 py-3">Duration</th>
                                <th className="px-4 py-3">Size</th>
                                <th className="px-4 py-3">Uploaded</th>
                                <th className="px-4 py-3 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-card-line">
                            {READY_VIDEOS.length === 0 && (
                                <tr>
                                    <td colSpan={7} className="px-4 py-6 text-center text-muted-foreground-1">
                                        No videos ready to analyze yet.
                                    </td>
                                </tr>
                            )}
                            {READY_VIDEOS.map((video) => {
                                const queued = queuedIds.includes(video.id);

                                return (
                                    <tr key={video.id}>
                                        <td className="px-4 py-3 font-medium text-foreground">{video.title}</td>
                                        <td className="px-4 py-3 text-muted-foreground-1">{video.workspace}</td>
                                        <td className="px-4 py-3">
                                            <ReadyBadge />
                                        </td>
                                        <td className="px-4 py-3 text-muted-foreground-1">{video.duration}</td>
                                        <td className="px-4 py-3 text-muted-foreground-1">{video.size}</td>
                                        <td className="px-4 py-3 text-muted-foreground-1">
                                            {new Date(video.uploadedAt).toLocaleDateString()}
                                        </td>
                                        <td className="px-4 py-3 text-right">
                                            <Button
                                                variant="secondary"
                                                disabled={queued}
                                                onClick={() => handleAnalyze(video.id)}
                                                className="py-2 px-3 text-xs"
                                            >
                                                {queued ? 'Queued' : 'Analyze'}
                                                <Sparkles className="size-3.5" strokeWidth={1.75} />
                                            </Button>
                                        </td>
                                    </tr>
                                );
                            })}
                        </tbody>
                    </table>
                </div>
            </Card>
        </AppLayout>
    );
}
