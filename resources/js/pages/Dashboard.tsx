import { useEffect, useState, type ReactNode } from 'react';
import { useNavigate, useSearchParams } from 'react-router-dom';
import type { ApexOptions } from 'apexcharts';
import { AppLayout } from '@/components/AppLayout';
import {
    AlertTriangle,
    CheckCircle2,
    Clock,
    Database,
    Download,
    Fingerprint,
    Flag,
    Folder,
    Lightbulb,
    Loader2,
    ShieldAlert,
    ShieldQuestion,
    Sparkles,
    Tag,
    Video,
    Wrench,
    XCircle,
} from 'lucide-react';
import { cssVarToValue } from 'preline/helpers/apexcharts';
import { api } from '../lib/api';
import { getErrorMessages } from '../lib/errors';
import { cn } from '../lib/utils';
import { useAuth } from '../lib/auth';
import { VIDEO_STATUS_STYLES as STATUS_STYLES, type VideoStatus } from '../lib/videoStatus';
import { formatChartDate, formatFileSize } from '../lib/format';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { ApexChart } from '@/components/ui/chart';
import { Progress } from '@/components/ui/progress';

type AnalysisJobType = 'object_detection' | 'threat_detection' | 'content_moderation' | 'text_detection';

const JOB_TYPE_LABELS: Record<AnalysisJobType, string> = {
    object_detection: 'Object detection',
    threat_detection: 'Threat detection',
    content_moderation: 'Content moderation',
    text_detection: 'Text detection',
};

type NeedsReviewItem = {
    video_id: number;
    video_title: string;
    workspace_name: string;
    type: AnalysisJobType;
    note: string | null;
    flagged_by: string | null;
    flagged_at: string;
};

type SpotlightItem = {
    video_id: number;
    video_title: string;
    workspace_name: string;
    type: 'threat' | 'moderation' | 'ai_content';
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
        stuck_processing_videos: number;
        total_inquiries: number;
        flagged_for_review: number;
        tool_jobs_run: number;
    };
    uploads_over_time: { date: string; count: number }[];
    safety_spotlight: {
        threats_detected: number;
        flagged_moderation: number;
        ai_content_flagged: number;
        items: SpotlightItem[];
    };
    workspace_leaderboard: LeaderboardWorkspace[];
    recent_activity: ActivityVideo[];
    top_labels: { label: string; occurrences: number }[];
    needs_review: NeedsReviewItem[];
    suggestions: Suggestion[];
};

type SuggestionType = 'failed_videos' | 'stuck_processing' | 'safety_flagged' | 'safety_clear' | 'needs_review' | 'processing' | 'no_uploads';

type SuggestionTone = 'warning' | 'success' | 'info';

type Suggestion = {
    type: SuggestionType;
    tone: SuggestionTone;
    text: string;
};

const SUGGESTION_TONE_STYLES: Record<SuggestionTone, string> = {
    warning: 'bg-red-500/10 text-red-600 dark:text-red-400',
    success: 'bg-green-500/10 text-green-600 dark:text-green-400',
    info: 'bg-primary/10 text-primary',
};

const SUGGESTION_ICONS: Record<SuggestionType, typeof XCircle> = {
    failed_videos: XCircle,
    stuck_processing: AlertTriangle,
    safety_flagged: ShieldAlert,
    safety_clear: CheckCircle2,
    needs_review: Flag,
    processing: Loader2,
    no_uploads: Clock,
};

const SEVERITY_STYLES: Record<string, string> = {
    CRITICAL: 'bg-red-100 text-red-800 dark:bg-red-500/20 dark:text-red-400',
    HIGH: 'bg-red-100 text-red-800 dark:bg-red-500/20 dark:text-red-400',
    MEDIUM: 'bg-amber-100 text-amber-800 dark:bg-amber-500/20 dark:text-amber-400',
    LOW: 'bg-slate-100 text-slate-800 dark:bg-slate-500/20 dark:text-slate-400',
    NONE: 'bg-slate-100 text-slate-800 dark:bg-slate-500/20 dark:text-slate-400',
};

function SuggestionBox({ suggestion }: { suggestion: Suggestion }) {
    const Icon = SUGGESTION_ICONS[suggestion.type];

    return (
        <div className="flex items-start gap-2.5 rounded-lg bg-layer p-3">
            <div className={cn('flex size-7 shrink-0 items-center justify-center rounded-lg', SUGGESTION_TONE_STYLES[suggestion.tone])}>
                <Icon className={cn('size-4', suggestion.type === 'processing' && 'animate-spin')} strokeWidth={1.75} />
            </div>
            <p className="text-sm text-foreground">{suggestion.text}</p>
        </div>
    );
}

function StatCell({ icon, label, value, tone = 'default' }: { icon: ReactNode; label: string; value: string; tone?: 'default' | 'warning' }) {
    return (
        <div className="flex items-center gap-3 bg-card px-5 py-4">
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

const SPOTLIGHT_ICONS: Record<SpotlightItem['type'], typeof ShieldAlert> = {
    threat: ShieldAlert,
    moderation: ShieldQuestion,
    ai_content: Fingerprint,
};

const SPOTLIGHT_LABELS: Record<SpotlightItem['type'], string> = {
    threat: 'Threat detected',
    moderation: 'Moderation flag',
    ai_content: 'AI-generated content',
};

function SpotlightRow({ item, onClick }: { item: SpotlightItem; onClick: () => void }) {
    const Icon = SPOTLIGHT_ICONS[item.type];

    return (
        <button onClick={onClick} className="flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-left hover:bg-layer">
            <div className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-red-500/10 text-red-600 dark:text-red-400">
                <Icon className="size-4" strokeWidth={1.75} />
            </div>
            <div className="min-w-0 flex-1">
                <p className="truncate text-sm font-medium text-foreground">{item.video_title}</p>
                <p className="truncate text-xs text-muted-foreground-1">
                    {item.workspace_name} · {SPOTLIGHT_LABELS[item.type]}
                </p>
            </div>
            <span className={cn('shrink-0 rounded-full px-2 py-0.5 text-xs font-medium', SEVERITY_STYLES[item.severity] ?? SEVERITY_STYLES.LOW)}>
                {item.severity}
            </span>
        </button>
    );
}

function NeedsReviewRow({ item, onClick }: { item: NeedsReviewItem; onClick: () => void }) {
    return (
        <button onClick={onClick} className="flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-left hover:bg-layer">
            <div className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-amber-500/10 text-amber-600 dark:text-amber-400">
                <Flag className="size-4" strokeWidth={1.75} />
            </div>
            <div className="min-w-0 flex-1">
                <p className="truncate text-sm font-medium text-foreground">{item.video_title}</p>
                <p className="truncate text-xs text-muted-foreground-1">
                    {item.workspace_name} · {JOB_TYPE_LABELS[item.type] ?? item.type}
                    {item.flagged_by && ` · flagged by ${item.flagged_by}`}
                </p>
            </div>
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

function TopLabelRow({ label, occurrences, maxOccurrences }: { label: string; occurrences: number; maxOccurrences: number }) {
    const percent = maxOccurrences > 0 ? (occurrences / maxOccurrences) * 100 : 0;

    return (
        <div className="flex flex-col gap-1.5 px-3 py-2.5">
            <div className="flex items-center justify-between gap-2">
                <p className="truncate text-sm font-medium text-foreground">{label}</p>
                <span className="shrink-0 text-xs text-muted-foreground-1">{occurrences.toLocaleString()}</span>
            </div>
            <Progress value={percent} />
        </div>
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

    const suggestions = dashboard?.suggestions ?? [];

    return (
        <AppLayout active="dashboard">
            {searchParams.get('verified') === '1' && (
                <div className="mb-4 rounded-lg border border-green-200 bg-green-100 p-4 text-sm text-green-800 dark:border-green-900 dark:bg-green-500/20 dark:text-green-400">
                    Your email has been verified.
                </div>
            )}

            <div className="flex items-start justify-between gap-4">
                <div>
                    <h1 className="font-heading text-2xl font-medium">Dashboard</h1>
                    <p className="mt-1 text-sm text-muted-foreground-1">
                        Welcome, {user?.name}. Here's what's happening across your workspaces.
                    </p>
                </div>
                {dashboard && dashboard.stats.total_workspaces > 0 && (
                    <Button variant="secondary" onClick={() => window.open('/api/dashboard/report', '_blank')}>
                        <Download className="size-4" strokeWidth={1.75} />
                        Download PDF
                    </Button>
                )}
            </div>

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

            {loading && (
                <div className="mt-10 flex flex-col items-center gap-2 text-center">
                    <Loader2 className="size-6 animate-spin text-primary" strokeWidth={1.75} />
                    <p className="text-sm text-muted-foreground-1">Loading…</p>
                </div>
            )}

            {!loading && dashboard && dashboard.stats.total_workspaces === 0 && (
                <Card className="mt-6 items-center gap-3 p-10 text-center">
                    <Folder className="size-8 text-muted-foreground-2" strokeWidth={1.5} />
                    <p className="text-sm text-muted-foreground-1">You don't belong to any workspaces yet.</p>
                    <Button onClick={() => navigate('/workspaces')}>Go to workspaces</Button>
                </Card>
            )}

            {!loading && dashboard && dashboard.stats.total_workspaces > 0 && (
                <>
                    {/* Compact stat grid, in one card instead of separate cards per stat. Uses the
                        gap-as-divider trick (bg-card-line container + gap-px + bg-card cells) instead
                        of divide-x/divide-y, since those only border the first-in-DOM child and would
                        wrongly draw a left border on the first cell of every wrapped row. 2 columns at
                        medium widths divides the 6 stats evenly (3 full rows, no dangling empty cell);
                        all 6 sit on one row only at 1220px+, where there's enough width for them not to
                        feel squeezed. */}
                    <Card className="mt-6 grid grid-cols-1 gap-px overflow-hidden bg-card-line sm:grid-cols-2 min-[1220px]:grid-cols-6">
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
                        <StatCell
                            icon={<Sparkles className="size-5" strokeWidth={1.75} />}
                            label="Inquiries asked"
                            value={String(dashboard.stats.total_inquiries)}
                        />
                    </Card>

                    {/* Kept separate from the 6-stat grid above rather than added as a 7th
                        cell, so that grid's even-division math (see its own comment) stays
                        accurate instead of silently going stale. */}
                    <Card className="mt-4 grid grid-cols-1 gap-px overflow-hidden bg-card-line sm:max-w-xs">
                        <StatCell
                            icon={<Wrench className="size-5" strokeWidth={1.75} />}
                            label="Tool jobs run"
                            value={String(dashboard.stats.tool_jobs_run)}
                        />
                    </Card>

                    {/* Suggestions + safety spotlight + needs review, side by side */}
                    <div className="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-3">
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <Lightbulb className="size-5 text-primary" strokeWidth={1.75} />
                                    Suggestions
                                </CardTitle>
                                <CardDescription>Quick tips based on your account</CardDescription>
                            </CardHeader>
                            <CardContent className="flex flex-col gap-2 pt-0">
                                {suggestions.length === 0 ? (
                                    <p className="py-8 text-center text-sm text-muted-foreground-1">Nothing to flag right now.</p>
                                ) : (
                                    suggestions.map((suggestion) => <SuggestionBox key={suggestion.text} suggestion={suggestion} />)
                                )}
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <ShieldAlert className="size-5 text-red-600 dark:text-red-400" strokeWidth={1.75} />
                                    Safety spotlight
                                </CardTitle>
                                <CardDescription>
                                    {dashboard.safety_spotlight.threats_detected} threat
                                    {dashboard.safety_spotlight.threats_detected === 1 ? '' : 's'} ·{' '}
                                    {dashboard.safety_spotlight.flagged_moderation} moderation flag
                                    {dashboard.safety_spotlight.flagged_moderation === 1 ? '' : 's'} ·{' '}
                                    {dashboard.safety_spotlight.ai_content_flagged} AI-generated, across all workspaces
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="flex flex-col gap-1 pt-0">
                                {dashboard.safety_spotlight.items.length === 0 ? (
                                    <div className="flex flex-col items-center gap-2 py-8 text-center">
                                        <CheckCircle2 className="size-6 text-green-600 dark:text-green-400" strokeWidth={1.75} />
                                        <p className="text-sm text-muted-foreground-1">No safety flags right now.</p>
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

                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <Flag className="size-5 text-amber-600 dark:text-amber-400" strokeWidth={1.75} />
                                    Needs review
                                </CardTitle>
                                <CardDescription>
                                    {dashboard.stats.flagged_for_review} result
                                    {dashboard.stats.flagged_for_review === 1 ? '' : 's'} flagged by your team, across all
                                    workspaces
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="flex flex-col gap-1 pt-0">
                                {dashboard.needs_review.length === 0 ? (
                                    <div className="flex flex-col items-center gap-2 py-8 text-center">
                                        <CheckCircle2 className="size-6 text-green-600 dark:text-green-400" strokeWidth={1.75} />
                                        <p className="text-sm text-muted-foreground-1">No flagged results right now.</p>
                                    </div>
                                ) : (
                                    dashboard.needs_review.map((item, index) => (
                                        <NeedsReviewRow
                                            key={`${item.video_id}-${item.type}-${index}`}
                                            item={item}
                                            onClick={() => navigate(`/videos/${item.video_id}/results`)}
                                        />
                                    ))
                                )}
                            </CardContent>
                        </Card>
                    </div>

                    {/* Workspace comparison + recent activity + top labels, as lists rather than more charts */}
                    <div className="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-3">
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

                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <Tag className="size-5 text-primary" strokeWidth={1.75} />
                                    Top detected labels
                                </CardTitle>
                                <CardDescription>Across all workspaces</CardDescription>
                            </CardHeader>
                            <CardContent className="flex flex-col gap-1 pt-0">
                                {dashboard.top_labels.length === 0 ? (
                                    <p className="py-8 text-center text-sm text-muted-foreground-1">No detections yet.</p>
                                ) : (
                                    dashboard.top_labels.map((item) => (
                                        <TopLabelRow
                                            key={item.label}
                                            label={item.label}
                                            occurrences={item.occurrences}
                                            maxOccurrences={dashboard.top_labels[0].occurrences}
                                        />
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
