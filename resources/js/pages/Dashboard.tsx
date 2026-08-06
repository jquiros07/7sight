import type { ApexOptions } from 'apexcharts';
import { CheckCircle2, Clock, Folder, Loader2, Tag, TrendingDown, TrendingUp, Trophy, Video } from 'lucide-react';
import { type ReactNode } from 'react';
import { useSearchParams } from 'react-router-dom';
import { cssVarToValue } from 'preline/helpers/apexcharts';
import { AppLayout } from '@/components/AppLayout';
import { ApexChart } from '@/components/ui/chart';
import { useAuth } from '../lib/auth';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';

// Dashboard data is mocked for now — nothing here is wired to a real endpoint yet.

type Trend = { direction: 'up' | 'down'; value: string };

const STATS: { icon: ReactNode; label: string; value: string; trend?: Trend; caption?: string }[] = [
    { icon: <Video className="size-5" strokeWidth={1.75} />, label: 'Total videos', value: '128', trend: { direction: 'up', value: '12%' } },
    { icon: <Folder className="size-5" strokeWidth={1.75} />, label: 'Workspaces', value: '6', caption: 'across your account' },
    {
        icon: <CheckCircle2 className="size-5" strokeWidth={1.75} />,
        label: 'Analyzed',
        value: '94',
        trend: { direction: 'up', value: '8%' },
    },
    { icon: <Loader2 className="size-5" strokeWidth={1.75} />, label: 'Processing now', value: '5', caption: 'active jobs' },
];

const INSIGHTS: { icon: ReactNode; label: string; value: string; progress?: number }[] = [
    { icon: <Trophy className="size-4" strokeWidth={1.75} />, label: 'Most active workspace', value: 'Marketing · 42 videos' },
    { icon: <Clock className="size-4" strokeWidth={1.75} />, label: 'Avg. analysis time', value: '2m 14s' },
    { icon: <CheckCircle2 className="size-4" strokeWidth={1.75} />, label: 'Completion rate', value: '87%', progress: 87 },
    { icon: <Tag className="size-4" strokeWidth={1.75} />, label: 'Top content type', value: 'Product demos' },
];

function last14Days(): string[] {
    return Array.from({ length: 14 }, (_, i) => {
        const date = new Date();
        date.setDate(date.getDate() - (13 - i));
        return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
    });
}

const UPLOADED_SERIES = [4, 6, 5, 8, 7, 9, 6, 10, 8, 11, 9, 13, 10, 12];
const ANALYZED_SERIES = [3, 4, 5, 6, 6, 7, 6, 8, 7, 9, 8, 10, 9, 11];

function StatCard({ icon, label, value, trend, caption }: (typeof STATS)[number]) {
    return (
        <Card className="flex items-center gap-4 p-5">
            <div className="flex size-11 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">{icon}</div>
            <div>
                <p className="text-sm text-muted-foreground-1">{label}</p>
                <div className="flex items-center gap-2">
                    <p className="text-2xl font-semibold text-foreground">{value}</p>
                    {trend && (
                        <span
                            className={`flex items-center gap-0.5 text-xs font-medium ${
                                trend.direction === 'up' ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'
                            }`}
                        >
                            {trend.direction === 'up' ? (
                                <TrendingUp className="size-3.5" strokeWidth={1.75} />
                            ) : (
                                <TrendingDown className="size-3.5" strokeWidth={1.75} />
                            )}
                            {trend.value}
                        </span>
                    )}
                </div>
                {caption && <p className="text-xs text-muted-foreground-2">{caption}</p>}
            </div>
        </Card>
    );
}

export default function Dashboard() {
    const { user } = useAuth();
    const [searchParams] = useSearchParams();

    const primary = cssVarToValue('--color-primary') ?? '#0891b2';
    const primaryLight = cssVarToValue('--color-chart-3') ?? '#67e8f9';
    const foregroundMuted = cssVarToValue('--color-muted-foreground-1') ?? '#71717a';
    const gridLine = cssVarToValue('--color-card-line') ?? '#e4e4e7';

    const activitySeries = [
        { name: 'Uploaded', data: UPLOADED_SERIES },
        { name: 'Analyzed', data: ANALYZED_SERIES },
    ];

    const activityOptions: ApexOptions = {
        chart: { fontFamily: 'inherit', foreColor: foregroundMuted },
        colors: [primary, primaryLight],
        stroke: { curve: 'smooth', width: 2 },
        fill: { type: 'gradient', gradient: { opacityFrom: 0.35, opacityTo: 0.05 } },
        dataLabels: { enabled: false },
        legend: { position: 'top', horizontalAlign: 'right' },
        grid: { borderColor: gridLine, strokeDashArray: 4 },
        xaxis: { categories: last14Days(), labels: { rotate: 0 }, tickAmount: 6 },
        yaxis: { labels: { formatter: (v) => `${Math.round(v)}` } },
        tooltip: { theme: 'dark' },
    };

    const statusSeries = [58, 23, 12, 7];
    const statusOptions: ApexOptions = {
        chart: { fontFamily: 'inherit', foreColor: foregroundMuted },
        labels: ['Completed', 'Processing', 'Queued', 'Failed'],
        colors: ['#22c55e', primary, '#f59e0b', '#ef4444'],
        legend: { position: 'bottom' },
        dataLabels: { enabled: true, formatter: (v: number) => `${Math.round(v)}%` },
        plotOptions: { pie: { donut: { labels: { show: true, total: { show: true, label: 'Videos' } } } } },
    };

    const workspaceSeries = [{ name: 'Videos', data: [42, 31, 24, 18, 9, 4] }];
    const workspaceOptions: ApexOptions = {
        chart: { fontFamily: 'inherit', foreColor: foregroundMuted },
        colors: [primary],
        plotOptions: { bar: { borderRadius: 6, columnWidth: '55%' } },
        dataLabels: { enabled: false },
        grid: { borderColor: gridLine, strokeDashArray: 4 },
        xaxis: { categories: ['Marketing', 'Research', 'Operations', 'HR', 'Product', 'Support'] },
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
            <p className="mt-1 text-sm text-muted-foreground-1">Welcome, {user?.name}.</p>

            {/* Top: stat cards */}
            <div className="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                {STATS.map((stat) => (
                    <StatCard key={stat.label} {...stat} />
                ))}
            </div>

            {/* Middle: activity graph + insights */}
            <div className="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-3">
                <Card className="lg:col-span-2">
                    <CardHeader>
                        <CardTitle>Upload & analysis activity</CardTitle>
                        <CardDescription>Last 14 days</CardDescription>
                    </CardHeader>
                    <CardContent className="pt-0">
                        <ApexChart type="area" height={300} options={activityOptions} series={activitySeries} />
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Insights</CardTitle>
                        <CardDescription>This week at a glance</CardDescription>
                    </CardHeader>
                    <CardContent className="flex flex-col gap-4 pt-0">
                        {INSIGHTS.map((insight) => (
                            <div key={insight.label} className="flex flex-col gap-1.5">
                                <div className="flex items-center gap-2 text-muted-foreground-1">
                                    {insight.icon}
                                    <span className="text-xs">{insight.label}</span>
                                </div>
                                <p className="text-sm font-medium text-foreground">{insight.value}</p>
                                {insight.progress !== undefined && (
                                    <div className="h-1.5 w-full overflow-hidden rounded-full bg-layer">
                                        <div className="h-full rounded-full bg-primary" style={{ width: `${insight.progress}%` }} />
                                    </div>
                                )}
                            </div>
                        ))}
                    </CardContent>
                </Card>
            </div>

            {/* Bottom: breakdown graphs */}
            <div className="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-2">
                <Card>
                    <CardHeader>
                        <CardTitle>Analysis status</CardTitle>
                        <CardDescription>Across all workspaces</CardDescription>
                    </CardHeader>
                    <CardContent className="pt-0">
                        <ApexChart type="donut" height={300} options={statusOptions} series={statusSeries} />
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Videos by workspace</CardTitle>
                        <CardDescription>Total uploaded</CardDescription>
                    </CardHeader>
                    <CardContent className="pt-0">
                        <ApexChart type="bar" height={300} options={workspaceOptions} series={workspaceSeries} />
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}