import { useEffect, useState, type ReactNode } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import type { ApexOptions } from 'apexcharts';
import { AppLayout } from '@/components/AppLayout';
import { CheckCircle2, ChevronLeft, Database, Loader2, ShieldAlert, ShieldQuestion, Video } from 'lucide-react';
import { cssVarToValue } from 'preline/helpers/apexcharts';
import { api } from '../lib/api';
import { getErrorMessages } from '../lib/errors';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { ApexChart } from '@/components/ui/chart';

type WorkspaceDashboardData = {
    workspace: { id: number; name: string };
    stats: {
        total_videos: number;
        total_storage_bytes: number;
        completed_analyses: number;
        processing_now: number;
    };
    uploads_over_time: { date: string; count: number }[];
    videos_by_status: { uploaded: number; processing: number; ready: number; failed: number };
    jobs_by_type: { object_detection: number; threat_detection: number; content_moderation: number };
    top_labels: { label: string; occurrences: number }[];
    insight_flags: { threats_detected: number; flagged_moderation: number };
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
        xaxis: { categories: ['Object detection', 'Threat detection', 'Content moderation'] },
        tooltip: { theme: 'dark' },
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

            {loading && <p className="mt-4 text-sm text-muted-foreground-1">Loading…</p>}

            {!loading && dashboard && (
                <>
                    <h1 className="mt-4 font-heading text-2xl font-medium">{dashboard.workspace.name}</h1>
                    <p className="mt-1 text-sm text-muted-foreground-1">Dashboard</p>

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

                    <div className="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-2">
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
                </>
            )}
        </AppLayout>
    );
}
