import { useEffect, useState, type ReactNode } from 'react';
import { useNavigate, useSearchParams } from 'react-router-dom';
import type { ApexOptions } from 'apexcharts';
import { AppLayout } from '@/components/AppLayout';
import {
    CheckCircle2,
    Database,
    Folder,
    Loader2,
    ShieldAlert,
    ShieldQuestion,
    Video,
    XCircle,
} from 'lucide-react';
import { cssVarToValue } from 'preline/helpers/apexcharts';
import { api } from '../lib/api';
import { getErrorMessages } from '../lib/errors';
import { cn } from '../lib/utils';
import { useAuth } from '../lib/auth';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { ApexChart } from '@/components/ui/chart';

type VideoStatus = 'uploaded' | 'processing' | 'ready' | 'failed';

type SpotlightItem = {
    video_id: number;
    video_title: string;
    workspace_name: string;
    type: 'threat' | 'moderation';
    severity: string;
};

type LeaderboardWorkspace = {
    id: number;
    name: string;
    total_videos: number;
    failed_videos: number;
    flagged_count: number;
    last_activity_at: string | null;
};

type ActivityVideo = {
    id: number;
    title: string;
    workspace_name: string;
    status: VideoStatus;
    created_at: string;
};

type DashboardData = {
    stats: {
        total_workspaces: number;
        total_videos: number;
        total_storage_bytes: number;
        failed_videos: number;
        processing_videos: number;
    };
    uploads_over_time: { date: string; count: number }[];
    safety_spotlight: {
        threats_detected: number;
        flagged_moderation: number;
        items: SpotlightItem[];
    };
    workspace_leaderboard: LeaderboardWorkspace[];
    recent_activity: ActivityVideo[];
};

const SEVERITY_STYLES: Record<string, string> = {
    CRITICAL: 'bg-red-100 text-red-800 dark:bg-red-500/20 dark:text-red-400',
    HIGH: 'bg-red-100 text-red-800 dark:bg-red-500/20 dark:text-red-400',
    MEDIUM: 'bg-amber-100 text-amber-800 dark:bg-amber-500/20 dark:text-amber-400',
    LOW: 'bg-slate-100 text-slate-800 dark:bg-slate-500/20 dark:text-slate-400',
    NONE: 'bg-slate-100 text-slate-800 dark:bg-slate-500/20 dark:text-slate-400',
};

const STATUS_STYLES: Record<VideoStatus, { label: string; className: string }> = {
    uploaded: { label: 'Uploaded', className: 'bg-violet-100 text-violet-800 dark:bg-violet-500/20 dark:text-violet-400' },
    processing: { label: 'Processing', className: 'bg-amber-100 text-amber-800 dark:bg-amber-500/20 dark:text-amber-400' },
    ready: { label: 'Analyzed', className: 'bg-green-100 text-green-800 dark:bg-green-500/20 dark:text-green-400' },
    failed: { label: 'Failed', className: 'bg-red-100 text-red-800 dark:bg-red-500/20 dark:text-red-400' },
};

function formatFileSize(bytes: number): string {
    if (bytes === 0) return '0 B';
    const units = ['B', 'KB', 'MB', 'GB'];
    const i = Math.min(Math.floor(Math.log(bytes) / Math.log(1024)), units.length - 1);
    return `${(bytes / 1024 ** i).toFixed(i === 0 ? 0 : 1)} ${units[i]}`;
}

function formatChartDate(dateStr: string): string {
    const [year, month, day] = dateStr.split('-').map(Number);
    return new Date(year, month - 1, day).toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
}

function StatCell({ icon, label, value, tone = 'default' }: { icon: ReactNode; label: string; value: string; tone?: 'default' | 'warning' }) {
    return (
        <div className="flex flex-1 items-center gap-3 px-5 py-4">
            <div
                className={cn(
                    'flex size-10 shrink-0 items-center justify-center rounded-lg',
                    tone === 'warning' ? 'bg-red-500/10 text-red-600 dark:text-red-400' : 'bg-primary/10 text-primary',
                )}
            >
                {icon}
            </div>
            <div className="min-w-0">
                <p className="truncate text-xs text-muted-foreground-1">{label}</p>
                <p className="text-xl font-semibold text-foreground">{value}</p>
            </div>
        </div>
    );
}

function SpotlightRow({ item, onClick }: { item: SpotlightItem; onClick: () => void }) {
    const Icon = item.type === 'threat' ? ShieldAlert : ShieldQuestion;

    return (
        <button onClick={onClick} className="flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-left hover:bg-layer">
            <div className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-red-500/10 text-red-600 dark:text-red-400">
                <Icon className="size-4" strokeWidth={1.75} />
            </div>
            <div className="min-w-0 flex-1">
                <p className="truncate text-sm font-medium text-foreground">{item.video_title}</p>
                <p className="truncate text-xs text-muted-foreground-1">
                    {item.workspace_name} · {item.type === 'threat' ? 'Threat detected' : 'Moderation flag'}
                </p>
            </div>
            <span className={cn('shrink-0 rounded-full px-2 py-0.5 text-xs font-medium', SEVERITY_STYLES[item.severity] ?? SEVERITY_STYLES.LOW)}>
                {item.severity}
            </span>
        </button>
    );
}

function LeaderboardRow({ workspace, rank, onClick }: { workspace: LeaderboardWorkspace; rank: number; onClick: () => void }) {
    return (
        <button onClick={onClick} className="flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-left hover:bg-layer">
            <span className="flex size-7 shrink-0 items-center justify-center rounded-full bg-layer text-xs font-semibold text-muted-foreground-1">
                {rank}
            </span>
            <div className="min-w-0 flex-1">
                <p className="truncate text-sm font-medium text-foreground">{workspace.name}</p>
                <p className="truncate text-xs text-muted-foreground-1">
                    {workspace.total_videos} video{workspace.total_videos === 1 ? '' : 's'}
                    {workspace.last_activity_at && ` · last activity ${new Date(workspace.last_activity_at).toLocaleDateString()}`}
                </p>
            </div>
            <div className="flex shrink-0 items-center gap-1.5">
                {workspace.failed_videos > 0 && (
                    <span className="rounded-full bg-red-100 px-2 py-0.5 text-xs font-medium text-red-800 dark:bg-red-500/20 dark:text-red-400">
                        {workspace.failed_videos} failed
                    </span>
                )}
                {workspace.flagged_count > 0 && (
                    <span className="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800 dark:bg-amber-500/20 dark:text-amber-400">
                        {workspace.flagged_count} flagged
                    </span>
                )}
            </div>
        </button>
    );
}

function ActivityRow({ video, onClick }: { video: ActivityVideo; onClick: () => void }) {
    const status = STATUS_STYLES[video.status];

    return (
        <button onClick={onClick} className="flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-left hover:bg-layer">
            <div className="min-w-0 flex-1">
                <p className="truncate text-sm font-medium text-foreground">{video.title}</p>
                <p className="truncate text-xs text-muted-foreground-1">
                    {video.workspace_name} · {new Date(video.created_at).toLocaleDateString()}
                </p>
            </div>
            <span className={cn('shrink-0 rounded-full px-2 py-0.5 text-xs font-medium', status.className)}>{status.label}</span>
        </button>
    );
}

export default function Dashboard() {
    const { user } = useAuth();
    const navigate = useNavigate();
    const [searchParams] = useSearchParams();

    const [dashboard, setDashboard] = useState<DashboardData | null>(null);
    const [loading, setLoading] = useState(true);
    const [loadError, setLoadError] = useState<string[]>([]);

    useEffect(() => {
        api.get<DashboardData>('/api/dashboard')
            .then((res) => setDashboard(res.data))
            .catch((err) => setLoadError(getErrorMessages(err)))
            .finally(() => setLoading(false));
    }, []);

    const primary = cssVarToValue('--color-primary') ?? '#0891b2';
    const foregroundMuted = cssVarToValue('--color-muted-foreground-1') ?? '#71717a';
    const gridLine = cssVarToValue('--color-card-line') ?? '#e4e4e7';

    const uploadsOptions: ApexOptions = {
        chart: { fontFamily: 'inherit', foreColor: foregroundMuted, toolbar: { show: false } },
        colors: [primary],
        stroke: { curve: 'smooth', width: 2 },
        fill: { type: 'gradient', gradient: { opacityFrom: 0.35, opacityTo: 0.05 } },
        dataLabels: { enabled: false },
        grid: { borderColor: gridLine, strokeDashArray: 4 },
        xaxis: { categories: dashboard?.uploads_over_time.map((d) => formatChartDate(d.date)) ?? [], labels: { rotate: 0 }, tickAmount: 6 },
        yaxis: { labels: { formatter: (v) => `${Math.round(v)}` } },
        tooltip: { theme: 'dark' },
    };

    return (
        <AppLayout active="dashboard">
            {searchParams.get('verified') === '1' && (
                <div className="mb-4 rounded-lg border border-green-200 bg-green-100 p-4 text-sm text-green-800 dark:border-green-900 dark:bg-green-500/20 dark:text-green-400">
                    Your email has been verified.
                </div>
            )}

            <h1 className="font-heading text-2xl font-medium">Dashboard</h1>
            <p className="mt-1 text-sm text-muted-foreground-1">Welcome, {user?.name}. Here's what's happening across your workspaces.</p>

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

            {loading && <p className="mt-4 text-sm text-muted-foreground-1">Loading…</p>}

            {!loading && dashboard && dashboard.stats.total_workspaces === 0 && (
                <Card className="mt-6 items-center gap-3 p-10 text-center">
                    <Folder className="size-8 text-muted-foreground-2" strokeWidth={1.5} />
                    <p className="text-sm text-muted-foreground-1">You don't belong to any workspaces yet.</p>
                    <Button onClick={() => navigate('/workspaces')}>Go to workspaces</Button>
                </Card>
            )}

            {!loading && dashboard && dashboard.stats.total_workspaces > 0 && (
                <>
                    {/* Compact stat strip, in one card instead of separate cards per stat */}
                    <Card className="mt-6 flex-row flex-wrap divide-x divide-card-line overflow-hidden">
                        <StatCell icon={<Folder className="size-5" strokeWidth={1.75} />} label="Workspaces" value={String(dashboard.stats.total_workspaces)} />
                        <StatCell icon={<Video className="size-5" strokeWidth={1.75} />} label="Total videos" value={String(dashboard.stats.total_videos)} />
                        <StatCell icon={<Database className="size-5" strokeWidth={1.75} />} label="Storage used" value={formatFileSize(dashboard.stats.total_storage_bytes)} />
                        <StatCell
                            icon={<Loader2 className="size-5" strokeWidth={1.75} />}
                            label="Processing now"
                            value={String(dashboard.stats.processing_videos)}
                        />
                        <StatCell
                            icon={<XCircle className="size-5" strokeWidth={1.75} />}
                            label="Failed videos"
                            value={String(dashboard.stats.failed_videos)}
                            tone={dashboard.stats.failed_videos > 0 ? 'warning' : 'default'}
                        />
                    </Card>

                    {/* Safety spotlight: the account-wide signal the workspace dashboard can't show */}
                    <Card className="mt-4">
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <ShieldAlert className="size-5 text-red-600 dark:text-red-400" strokeWidth={1.75} />
                                Safety spotlight
                            </CardTitle>
                            <CardDescription>
                                {dashboard.safety_spotlight.threats_detected + dashboard.safety_spotlight.flagged_moderation > 0
                                    ? `${dashboard.safety_spotlight.threats_detected} threat${dashboard.safety_spotlight.threats_detected === 1 ? '' : 's'} · ${dashboard.safety_spotlight.flagged_moderation} moderation flag${dashboard.safety_spotlight.flagged_moderation === 1 ? '' : 's'}, across all workspaces`
                                    : 'Videos worth a human look, across all workspaces'}
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="flex flex-col gap-1 pt-0">
                            {dashboard.safety_spotlight.items.length === 0 ? (
                                <div className="flex flex-col items-center gap-2 py-8 text-center">
                                    <CheckCircle2 className="size-6 text-green-600 dark:text-green-400" strokeWidth={1.75} />
                                    <p className="text-sm text-muted-foreground-1">No threats or moderation flags right now.</p>
                                </div>
                            ) : (
                                dashboard.safety_spotlight.items.map((item) => (
                                    <SpotlightRow
                                        key={`${item.type}-${item.video_id}`}
                                        item={item}
                                        onClick={() => navigate(`/videos/${item.video_id}/results`)}
                                    />
                                ))
                            )}
                        </CardContent>
                    </Card>

                    {/* Workspace comparison + recent activity, as lists rather than more charts */}
                    <div className="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-2">
                        <Card>
                            <CardHeader>
                                <CardTitle>Workspaces</CardTitle>
                                <CardDescription>Ranked by video count</CardDescription>
                            </CardHeader>
                            <CardContent className="flex flex-col gap-1 pt-0">
                                {dashboard.workspace_leaderboard.map((workspace, index) => (
                                    <LeaderboardRow
                                        key={workspace.id}
                                        workspace={workspace}
                                        rank={index + 1}
                                        onClick={() => navigate(`/workspaces/${workspace.id}/dashboard`)}
                                    />
                                ))}
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle>Recent activity</CardTitle>
                                <CardDescription>Latest uploads across your workspaces</CardDescription>
                            </CardHeader>
                            <CardContent className="flex flex-col gap-1 pt-0">
                                {dashboard.recent_activity.length === 0 ? (
                                    <p className="py-8 text-center text-sm text-muted-foreground-1">No videos uploaded yet.</p>
                                ) : (
                                    dashboard.recent_activity.map((video) => (
                                        <ActivityRow key={video.id} video={video} onClick={() => navigate(`/videos/${video.id}/results`)} />
                                    ))
                                )}
                            </CardContent>
                        </Card>
                    </div>

                    {/* One trend chart for continuity, not a wall of them */}
                    <Card className="mt-4">
                        <CardHeader>
                            <CardTitle>Upload trend</CardTitle>
                            <CardDescription>Last 14 days, across all workspaces</CardDescription>
                        </CardHeader>
                        <CardContent className="pt-0">
                            <ApexChart
                                type="area"
                                height={220}
                                options={uploadsOptions}
                                series={[{ name: 'Uploaded', data: dashboard.uploads_over_time.map((d) => d.count) }]}
                            />
                        </CardContent>
                    </Card>
                </>
            )}
        </AppLayout>
    );
}
