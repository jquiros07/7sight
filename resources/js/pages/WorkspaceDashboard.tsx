import { useEffect, useState, type ReactNode } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import type { ApexOptions } from 'apexcharts';
import { AppLayout } from '@/components/AppLayout';
import {
    CheckCircle2,
    ChevronLeft,
    Clock,
    Database,
    Download,
    Fingerprint,
    Flag,
    Loader2,
    ShieldAlert,
    ShieldQuestion,
    Sparkles,
    Video,
    Wrench,
    XCircle,
} from 'lucide-react';
import { cssVarToValue } from 'preline/helpers/apexcharts';
import { api } from '../lib/api';
import { getErrorMessages } from '../lib/errors';
import { formatChartDate, formatFileSize } from '../lib/format';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { ApexChart } from '@/components/ui/chart';

type WorkspaceInsightSummary = {
    summary: string;
    highlights: string[];
    generated_at: string;
};

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
    type: AnalysisJobType;
    note: string | null;
    flagged_by: string | null;
    flagged_at: string;
};

type WorkspaceDashboardData = {
    workspace: { id: number; name: string };
    insight_summary: WorkspaceInsightSummary | null;
    stats: {
        total_videos: number;
        total_storage_bytes: number;
        completed_analyses: number;
        processing_now: number;
        failed_jobs: number;
        flagged_for_review: number;
        avg_processing_seconds: number | null;
        tool_jobs_run: number;
    };
    uploads_over_time: { date: string; count: number }[];
    videos_by_status: { uploaded: number; processing: number; ready: number; failed: number };
    jobs_by_type: { object_detection: number; threat_detection: number; content_moderation: number; text_detection: number };
    top_labels: { label: string; occurrences: number }[];
    insight_flags: { threats_detected: number; flagged_moderation: number; ai_generated_content_flagged: number };
    risk_level_breakdown: { LOW: number; MEDIUM: number; HIGH: number; CRITICAL: number };
    moderation_severity_breakdown: { NONE: number; LOW: number; MEDIUM: number; HIGH: number };
    needs_review: NeedsReviewItem[];
};

function formatDurationShort(totalSeconds: number | null): string {
    if (totalSeconds === null) return '—';
    const minutes = Math.floor(totalSeconds / 60);
    const seconds = Math.round(totalSeconds % 60);
    if (minutes === 0) return `${seconds}s`;
    return `${minutes}m ${seconds}s`;
}

function StatCard({ icon, label, value, caption, tone = 'default' }: { icon: ReactNode; label: string; value: number | string; caption?: string; tone?: 'default' | 'warning' }) {
    return (
        <Card className="flex items-center gap-4 p-5">
            <div
                className={
                    tone === 'warning'
                        ? 'flex size-11 shrink-0 items-center justify-center rounded-lg bg-red-500/10 text-red-600 dark:text-red-400'
                        : 'flex size-11 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary'
                }
            >
                {icon}
            </div>
            <div>
                <p className="text-sm text-muted-foreground-1">{label}</p>
                <p className="text-2xl font-semibold text-foreground">{value}</p>
                {caption && <p className="text-xs text-muted-foreground-2">{caption}</p>}
            </div>
        </Card>
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
                    {JOB_TYPE_LABELS[item.type] ?? item.type}
                    {item.flagged_by && ` · flagged by ${item.flagged_by}`}
                    {item.note && ` · "${item.note}"`}
                </p>
            </div>
        </button>
    );
}

function WorkspaceSummaryCard({ workspaceId, initialSummary }: { workspaceId: number; initialSummary: WorkspaceInsightSummary | null }) {
    const [summary, setSummary] = useState<WorkspaceInsightSummary | null>(initialSummary);
    const [generating, setGenerating] = useState(false);
    const [error, setError] = useState<string[]>([]);

    async function handleGenerate() {
        setGenerating(true);
        setError([]);
        try {
            const res = await api.post<WorkspaceInsightSummary>(`/api/workspaces/${workspaceId}/insight-summary`);
            setSummary(res.data);
        } catch (err) {
            setError(getErrorMessages(err));
        } finally {
            setGenerating(false);
        }
    }

    return (
        <Card className="border-primary/30 bg-primary/[0.03]">
            <CardHeader className="flex-row items-center justify-between gap-4 space-y-0">
                <div className="flex items-center gap-2">
                    <Sparkles className="size-4 text-primary" strokeWidth={1.75} />
                    <CardTitle className="text-base">Workspace summary</CardTitle>
                </div>
                <Button variant="secondary" disabled={generating} onClick={handleGenerate}>
                    {generating ? (
                        <>
                            <Loader2 className="size-4 animate-spin" strokeWidth={1.75} />
                            Generating…
                        </>
                    ) : summary ? (
                        'Refresh'
                    ) : (
                        'Generate'
                    )}
                </Button>
            </CardHeader>
            <CardContent className="flex flex-col gap-3">
                {error.length > 0 && (
                    <Alert variant="destructive" onDismiss={() => setError([])}>
                        <AlertDescription>
                            <ul className="list-disc space-y-1 pl-4">
                                {error.map((message) => (
                                    <li key={message}>{message}</li>
                                ))}
                            </ul>
                        </AlertDescription>
                    </Alert>
                )}

                {!summary && !generating && (
                    <p className="text-sm text-muted-foreground-1">
                        Generate an AI narrative summary of this workspace's analyzed videos.
                    </p>
                )}

                {generating && !summary && (
                    <div className="flex items-center gap-2 text-sm text-muted-foreground-1">
                        <Loader2 className="size-4 animate-spin" strokeWidth={1.75} />
                        Summarizing this workspace's videos…
                    </div>
                )}

                {summary && (
                    <>
                        <p className="text-sm text-foreground">{summary.summary}</p>
                        {summary.highlights.length > 0 && (
                            <ul className="list-disc space-y-1 pl-4 text-sm text-muted-foreground-1">
                                {summary.highlights.map((highlight) => (
                                    <li key={highlight}>{highlight}</li>
                                ))}
                            </ul>
                        )}
                        <p className="text-xs text-muted-foreground-2">Generated {new Date(summary.generated_at).toLocaleString()}</p>
                    </>
                )}
            </CardContent>
        </Card>
    );
}

export default function WorkspaceDashboard() {
    const { id } = useParams<{ id: string }>();
    const navigate = useNavigate();

    const [dashboard, setDashboard] = useState<WorkspaceDashboardData | null>(null);
    const [loading, setLoading] = useState(true);
    const [loadError, setLoadError] = useState<string[]>([]);

    useEffect(() => {
        api.get<WorkspaceDashboardData>(`/api/workspaces/${id}/dashboard`)
            .then((res) => setDashboard(res.data))
            .catch((err) => setLoadError(getErrorMessages(err)))
            .finally(() => setLoading(false));
    }, [id]);

    const primary = cssVarToValue('--color-primary') ?? '#0891b2';
    const foregroundMuted = cssVarToValue('--color-muted-foreground-1') ?? '#71717a';
    const gridLine = cssVarToValue('--color-card-line') ?? '#e4e4e7';

    const uploadsOptions: ApexOptions = {
        chart: { fontFamily: 'inherit', foreColor: foregroundMuted },
        colors: [primary],
        stroke: { curve: 'smooth', width: 2 },
        fill: { type: 'gradient', gradient: { opacityFrom: 0.35, opacityTo: 0.05 } },
        dataLabels: { enabled: false },
        grid: { borderColor: gridLine, strokeDashArray: 4 },
        xaxis: { categories: dashboard?.uploads_over_time.map((d) => formatChartDate(d.date)) ?? [], labels: { rotate: 0 }, tickAmount: 6 },
        yaxis: { labels: { formatter: (v) => `${Math.round(v)}` } },
        tooltip: { theme: 'dark' },
    };

    const statusOptions: ApexOptions = {
        chart: { fontFamily: 'inherit', foreColor: foregroundMuted },
        labels: ['Uploaded', 'Processing', 'Ready', 'Failed'],
        colors: [primary, '#f59e0b', '#22c55e', '#ef4444'],
        legend: { position: 'bottom' },
        dataLabels: { enabled: true, formatter: (v: number) => `${Math.round(v)}%` },
        plotOptions: { pie: { donut: { labels: { show: true, total: { show: true, label: 'Videos' } } } } },
    };

    const jobsByTypeOptions: ApexOptions = {
        chart: { fontFamily: 'inherit', foreColor: foregroundMuted },
        colors: [primary],
        plotOptions: { bar: { borderRadius: 6, columnWidth: '55%' } },
        dataLabels: { enabled: false },
        grid: { borderColor: gridLine, strokeDashArray: 4 },
        xaxis: { categories: ['Object detection', 'Threat detection', 'Content moderation', 'Text detection'] },
        tooltip: { theme: 'dark' },
    };

    const riskLevelOptions: ApexOptions = {
        chart: { fontFamily: 'inherit', foreColor: foregroundMuted },
        labels: ['Low', 'Medium', 'High', 'Critical'],
        colors: ['#22c55e', '#f59e0b', '#f97316', '#ef4444'],
        legend: { position: 'bottom' },
        dataLabels: { enabled: true, formatter: (v: number) => `${Math.round(v)}%` },
        plotOptions: { pie: { donut: { labels: { show: true, total: { show: true, label: 'Assessed' } } } } },
    };

    const moderationSeverityOptions: ApexOptions = {
        chart: { fontFamily: 'inherit', foreColor: foregroundMuted },
        labels: ['None', 'Low', 'Medium', 'High'],
        colors: ['#22c55e', primary, '#f59e0b', '#ef4444'],
        legend: { position: 'bottom' },
        dataLabels: { enabled: true, formatter: (v: number) => `${Math.round(v)}%` },
        plotOptions: { pie: { donut: { labels: { show: true, total: { show: true, label: 'Assessed' } } } } },
    };

    const topLabelsOptions: ApexOptions = {
        chart: { fontFamily: 'inherit', foreColor: foregroundMuted },
        colors: [primary],
        plotOptions: { bar: { borderRadius: 6, horizontal: true } },
        dataLabels: { enabled: false },
        grid: { borderColor: gridLine, strokeDashArray: 4 },
        xaxis: { categories: dashboard?.top_labels.map((l) => l.label) ?? [] },
        tooltip: { theme: 'dark' },
    };

    return (
        <AppLayout active="workspaces">
            <Button variant="secondary" onClick={() => navigate('/workspaces')}>
                <ChevronLeft className="size-4" strokeWidth={1.75} />
                Back to workspaces
            </Button>

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

            {!loading && dashboard && (
                <>
                    <div className="mt-4 flex items-start justify-between gap-4">
                        <div>
                            <h1 className="font-heading text-2xl font-medium">{dashboard.workspace.name}</h1>
                            <p className="mt-1 text-sm text-muted-foreground-1">Dashboard</p>
                        </div>
                        <Button
                            variant="secondary"
                            onClick={() => window.open(`/api/workspaces/${dashboard.workspace.id}/dashboard/report`, '_blank')}
                        >
                            <Download className="size-4" strokeWidth={1.75} />
                            Download PDF
                        </Button>
                    </div>

                    <div className="mt-6">
                        <WorkspaceSummaryCard workspaceId={dashboard.workspace.id} initialSummary={dashboard.insight_summary} />
                    </div>

                    <div className="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <StatCard icon={<Video className="size-5" strokeWidth={1.75} />} label="Total videos" value={dashboard.stats.total_videos} />
                        <StatCard
                            icon={<Database className="size-5" strokeWidth={1.75} />}
                            label="Storage used"
                            value={formatFileSize(dashboard.stats.total_storage_bytes)}
                        />
                        <StatCard
                            icon={<CheckCircle2 className="size-5" strokeWidth={1.75} />}
                            label="Completed analyses"
                            value={dashboard.stats.completed_analyses}
                        />
                        <StatCard
                            icon={<Loader2 className="size-5" strokeWidth={1.75} />}
                            label="Processing now"
                            value={dashboard.stats.processing_now}
                            caption="active jobs"
                        />
                    </div>

                    <div className="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-4">
                        <StatCard
                            icon={<ShieldAlert className="size-5" strokeWidth={1.75} />}
                            label="Threats detected"
                            value={dashboard.insight_flags.threats_detected}
                            caption="from generated AI insights"
                            tone={dashboard.insight_flags.threats_detected > 0 ? 'warning' : 'default'}
                        />
                        <StatCard
                            icon={<ShieldQuestion className="size-5" strokeWidth={1.75} />}
                            label="Flagged for moderation"
                            value={dashboard.insight_flags.flagged_moderation}
                            caption="from generated AI insights"
                            tone={dashboard.insight_flags.flagged_moderation > 0 ? 'warning' : 'default'}
                        />
                        <StatCard
                            icon={<Fingerprint className="size-5" strokeWidth={1.75} />}
                            label="AI-generated content"
                            value={dashboard.insight_flags.ai_generated_content_flagged}
                            caption="flagged videos"
                            tone={dashboard.insight_flags.ai_generated_content_flagged > 0 ? 'warning' : 'default'}
                        />
                        <StatCard
                            icon={<Flag className="size-5" strokeWidth={1.75} />}
                            label="Needs review"
                            value={dashboard.stats.flagged_for_review}
                            caption="flagged by your team"
                            tone={dashboard.stats.flagged_for_review > 0 ? 'warning' : 'default'}
                        />
                    </div>

                    <Card className="mt-4">
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <Flag className="size-5 text-amber-600 dark:text-amber-400" strokeWidth={1.75} />
                                Needs review
                            </CardTitle>
                            <CardDescription>Analysis results your team flagged as inaccurate</CardDescription>
                        </CardHeader>
                        <CardContent className="flex flex-col gap-1 pt-0">
                            {dashboard.needs_review.length === 0 ? (
                                <p className="py-8 text-center text-sm text-muted-foreground-1">No flagged results right now.</p>
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

                    <div className="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-3">
                        <StatCard
                            icon={<XCircle className="size-5" strokeWidth={1.75} />}
                            label="Failed jobs"
                            value={dashboard.stats.failed_jobs}
                            tone={dashboard.stats.failed_jobs > 0 ? 'warning' : 'default'}
                        />
                        <StatCard
                            icon={<Clock className="size-5" strokeWidth={1.75} />}
                            label="Avg. processing time"
                            value={formatDurationShort(dashboard.stats.avg_processing_seconds)}
                            caption="per completed job"
                        />
                        <StatCard
                            icon={<Wrench className="size-5" strokeWidth={1.75} />}
                            label="Tool jobs run"
                            value={dashboard.stats.tool_jobs_run}
                            caption="trims + resizes"
                        />
                    </div>

                    <div className="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-2">
                        <Card>
                            <CardHeader>
                                <CardTitle>Uploads</CardTitle>
                                <CardDescription>Last 14 days</CardDescription>
                            </CardHeader>
                            <CardContent className="pt-0">
                                <ApexChart
                                    type="area"
                                    height={300}
                                    options={uploadsOptions}
                                    series={[{ name: 'Uploaded', data: dashboard.uploads_over_time.map((d) => d.count) }]}
                                />
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle>Videos by status</CardTitle>
                                <CardDescription>Current state of this workspace's videos</CardDescription>
                            </CardHeader>
                            <CardContent className="pt-0">
                                <ApexChart
                                    type="donut"
                                    height={300}
                                    options={statusOptions}
                                    series={[
                                        dashboard.videos_by_status.uploaded,
                                        dashboard.videos_by_status.processing,
                                        dashboard.videos_by_status.ready,
                                        dashboard.videos_by_status.failed,
                                    ]}
                                />
                            </CardContent>
                        </Card>
                    </div>

                    <div className="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-2">
                        <Card>
                            <CardHeader>
                                <CardTitle>Analysis jobs by type</CardTitle>
                                <CardDescription>All jobs ever run</CardDescription>
                            </CardHeader>
                            <CardContent className="pt-0">
                                <ApexChart
                                    type="bar"
                                    height={300}
                                    options={jobsByTypeOptions}
                                    series={[
                                        {
                                            name: 'Jobs',
                                            data: [
                                                dashboard.jobs_by_type.object_detection,
                                                dashboard.jobs_by_type.threat_detection,
                                                dashboard.jobs_by_type.content_moderation,
                                                dashboard.jobs_by_type.text_detection,
                                            ],
                                        },
                                    ]}
                                />
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle>Top detected labels</CardTitle>
                                <CardDescription>Across all analyses in this workspace</CardDescription>
                            </CardHeader>
                            <CardContent className="pt-0">
                                {dashboard.top_labels.length > 0 ? (
                                    <ApexChart
                                        type="bar"
                                        height={300}
                                        options={topLabelsOptions}
                                        series={[{ name: 'Occurrences', data: dashboard.top_labels.map((l) => l.occurrences) }]}
                                    />
                                ) : (
                                    <p className="py-10 text-center text-sm text-muted-foreground-1">No detections yet.</p>
                                )}
                            </CardContent>
                        </Card>
                    </div>

                    <div className="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-2">
                        <Card>
                            <CardHeader>
                                <CardTitle>Risk level breakdown</CardTitle>
                                <CardDescription>Latest threat assessment per video</CardDescription>
                            </CardHeader>
                            <CardContent className="pt-0">
                                {dashboard.risk_level_breakdown.LOW +
                                    dashboard.risk_level_breakdown.MEDIUM +
                                    dashboard.risk_level_breakdown.HIGH +
                                    dashboard.risk_level_breakdown.CRITICAL >
                                0 ? (
                                    <ApexChart
                                        type="donut"
                                        height={300}
                                        options={riskLevelOptions}
                                        series={[
                                            dashboard.risk_level_breakdown.LOW,
                                            dashboard.risk_level_breakdown.MEDIUM,
                                            dashboard.risk_level_breakdown.HIGH,
                                            dashboard.risk_level_breakdown.CRITICAL,
                                        ]}
                                    />
                                ) : (
                                    <p className="py-10 text-center text-sm text-muted-foreground-1">No threat assessments yet.</p>
                                )}
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle>Moderation severity breakdown</CardTitle>
                                <CardDescription>Latest moderation assessment per video</CardDescription>
                            </CardHeader>
                            <CardContent className="pt-0">
                                {dashboard.moderation_severity_breakdown.NONE +
                                    dashboard.moderation_severity_breakdown.LOW +
                                    dashboard.moderation_severity_breakdown.MEDIUM +
                                    dashboard.moderation_severity_breakdown.HIGH >
                                0 ? (
                                    <ApexChart
                                        type="donut"
                                        height={300}
                                        options={moderationSeverityOptions}
                                        series={[
                                            dashboard.moderation_severity_breakdown.NONE,
                                            dashboard.moderation_severity_breakdown.LOW,
                                            dashboard.moderation_severity_breakdown.MEDIUM,
                                            dashboard.moderation_severity_breakdown.HIGH,
                                        ]}
                                    />
                                ) : (
                                    <p className="py-10 text-center text-sm text-muted-foreground-1">No moderation assessments yet.</p>
                                )}
                            </CardContent>
                        </Card>
                    </div>
                </>
            )}
        </AppLayout>
    );
}
