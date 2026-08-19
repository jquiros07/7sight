import { FormEvent, useEffect, useRef, useState } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import type { ApexOptions } from 'apexcharts';
import { AppLayout } from '@/components/AppLayout';
import { ChevronLeft, Download, Lightbulb, Loader2, PlayCircle, Sparkles } from 'lucide-react';
import { buildTooltip, type IBuildTooltipHelperOptions, type IChartProps } from 'preline/helpers/apexcharts';
import { varToColor } from 'preline/helpers/shared';
import { api } from '../lib/api';
import { cn } from '../lib/utils';
import { getErrorMessages } from '../lib/errors';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { ApexChart } from '@/components/ui/chart';
import { Input } from '@/components/ui/input';

type AnalysisJobType = 'object_detection' | 'threat_detection' | 'content_moderation' | 'ai_generated';
type AnalysisJobStatus = 'pending' | 'processing' | 'completed' | 'failed';

type AnalysisResult = {
    id: number;
    label: string;
    occurrences: number;
    avg_confidence: number;
    min_confidence: number;
    max_confidence: number;
    first_seen_at: string;
    last_seen_at: string;
};

type AnalysisJob = {
    id: number;
    type: AnalysisJobType;
    status: AnalysisJobStatus;
    error_message: string | null;
    created_at: string;
    results: AnalysisResult[];
};

type VideoDetail = {
    id: number;
    title: string;
    description: string | null;
    status: string;
    size: number;
    duration_seconds: number | null;
    created_at: string;
    analysis_jobs: AnalysisJob[];
    latest_insight: VideoInsightsResponse | null;
};

type DetectedObject = {
    label: string;
    count: number;
};

type ObjectDetectionAssessment = {
    summary: string;
    confidence: number;
    timestamp: number | null;
    objects: DetectedObject[];
    notable_observations: string[];
};

type RiskLevel = 'LOW' | 'MEDIUM' | 'HIGH' | 'CRITICAL';

type ThreatEvidence = {
    label: string;
    rekognition_confidence: number;
    timestamp: number;
};

type ThreatAssessment = {
    threat_detected: boolean;
    risk_level: RiskLevel;
    confidence: number;
    timestamp: number;
    evidence: ThreatEvidence[];
    summary: string;
    reasoning: string;
    // Optional: insights generated before this field existed won't have it.
    suggestions?: string[];
};

type ModerationStatus = 'SAFE' | 'REVIEW' | 'UNSAFE';
type ModerationSeverity = 'NONE' | 'LOW' | 'MEDIUM' | 'HIGH';

type ModerationAssessment = {
    status: ModerationStatus;
    severity: ModerationSeverity;
    confidence: number;
    timestamp: number | null;
    summary: string;
    reasoning: string;
    // Optional: insights generated before this field existed won't have it.
    suggestions?: string[];
};

type VideoInsightsResponse = {
    object_detection: ObjectDetectionAssessment | null;
    threat_assessment: ThreatAssessment | null;
    moderation: ModerationAssessment | null;
};

type InquiryEvidence = {
    label: string;
    timestamp: number | null;
    note: string;
};

type VideoInquiryAnswer = {
    answerable: boolean;
    answer: string;
    confidence: number;
    evidence: InquiryEvidence[];
    caveats: string | null;
};

type VideoInquiry = {
    id: number;
    question: string;
    answer: VideoInquiryAnswer;
    created_at: string;
};

const RISK_LEVEL_BADGES: Record<RiskLevel, string> = {
    LOW: 'bg-green-100 text-green-800 dark:bg-green-500/20 dark:text-green-400',
    MEDIUM: 'bg-amber-100 text-amber-800 dark:bg-amber-500/20 dark:text-amber-400',
    HIGH: 'bg-orange-100 text-orange-800 dark:bg-orange-500/20 dark:text-orange-400',
    CRITICAL: 'bg-red-600 text-white dark:bg-red-500/80',
};

const MODERATION_STATUS_BADGES: Record<ModerationStatus, string> = {
    SAFE: 'bg-green-100 text-green-800 dark:bg-green-500/20 dark:text-green-400',
    REVIEW: 'bg-amber-100 text-amber-800 dark:bg-amber-500/20 dark:text-amber-400',
    UNSAFE: 'bg-red-600 text-white dark:bg-red-500/80',
};

const TYPE_LABELS: Record<AnalysisJobType, string> = {
    object_detection: 'Object detection',
    threat_detection: 'Threat detection',
    content_moderation: 'Content moderation',
    ai_generated: 'AI generated',
};

const JOB_STATUS_BADGES: Record<AnalysisJobStatus, { label: string; className: string; icon?: 'dot' | 'spinner' }> = {
    pending: { label: 'Queued', className: 'bg-layer text-muted-foreground-1', icon: 'dot' },
    processing: {
        label: 'Processing',
        className: 'bg-amber-100 text-amber-800 dark:bg-amber-500/20 dark:text-amber-400',
        icon: 'spinner',
    },
    completed: {
        label: 'Completed',
        className: 'bg-green-100 text-green-800 dark:bg-green-500/20 dark:text-green-400',
        icon: 'dot',
    },
    failed: { label: 'Failed', className: 'bg-red-100 text-red-800 dark:bg-red-500/20 dark:text-red-400', icon: 'dot' },
};

function JobStatusBadge({ status }: { status: AnalysisJobStatus }) {
    const badge = JOB_STATUS_BADGES[status];

    return (
        <span className={cn('inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium', badge.className)}>
            {badge.icon === 'spinner' ? (
                <Loader2 className="size-3 animate-spin" strokeWidth={2} />
            ) : (
                <span className="size-1.5 rounded-full bg-current" />
            )}
            {badge.label}
        </span>
    );
}

function formatSeconds(value: string | number): string {
    const totalSeconds = Math.floor(Number(value));
    const minutes = Math.floor(totalSeconds / 60);
    const seconds = totalSeconds % 60;
    return `${minutes}:${String(seconds).padStart(2, '0')}`;
}

// Every place evidence carries a timestamp renders it through this button
// instead of plain text, so clicking any detection jumps the player to it.
function TimestampButton({ seconds, onSeek }: { seconds: number; onSeek: (seconds: number) => void }) {
    return (
        <button
            type="button"
            onClick={() => onSeek(seconds)}
            className="inline-flex items-center gap-1 font-medium text-primary hover:underline"
        >
            <PlayCircle className="size-3.5" strokeWidth={1.75} />
            {formatSeconds(seconds)}
        </button>
    );
}

function formatDuration(totalSeconds: number | null): string {
    if (totalSeconds === null) return '—';
    const hours = Math.floor(totalSeconds / 3600);
    const minutes = Math.floor((totalSeconds % 3600) / 60);
    const seconds = Math.floor(totalSeconds % 60);
    if (hours > 0) {
        return `${hours}:${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
    }
    return `${minutes}:${String(seconds).padStart(2, '0')}`;
}

function formatFileSize(bytes: number): string {
    if (bytes === 0) return '0 B';
    const units = ['B', 'KB', 'MB', 'GB'];
    const i = Math.min(Math.floor(Math.log(bytes) / Math.log(1024)), units.length - 1);
    return `${(bytes / 1024 ** i).toFixed(i === 0 ? 0 : 1)} ${units[i]}`;
}

// Caps how many bars the chart draws — with a lot of distinct labels an
// unbounded chart grows without limit (one video hit 80+ labels and the bar
// chart alone was taller than the rest of the page). The full table below
// still lists everything; the chart is just the headline trend.
const MAX_CHART_BARS = 12;

// A one-bar chart isn't a chart — only worth rendering once there's more than
// one label to compare.
function OccurrencesChart({ results }: { results: AnalysisResult[] }) {
    // This app doesn't wire up Preline's live theme-switch events, so we snapshot
    // the mode once at mount — same limitation the Dashboard charts already have.
    const mode = document.documentElement.classList.contains('dark') ? 'dark' : 'light';
    const suffix = mode === 'dark' ? '-inverse' : '';
    const primary = varToColor(`--chart-colors-primary${suffix}`) ?? '#0891b2';
    const yaxisLabelColor = varToColor(`--chart-colors-yaxis-labels${suffix}`) ?? '#a1a1aa';
    const gridBorder = varToColor(`--chart-colors-grid-border${suffix}`) ?? '#e4e4e7';

    const sorted = results.slice().sort((a, b) => b.occurrences - a.occurrences);
    const chartData = sorted.slice(0, MAX_CHART_BARS);

    const options: ApexOptions = {
        chart: { fontFamily: 'inherit' },
        colors: [primary],
        plotOptions: { bar: { horizontal: true, borderRadius: 4, barHeight: '55%' } },
        legend: { show: false },
        dataLabels: { enabled: false },
        stroke: { show: true, width: 2, colors: ['transparent'] },
        grid: { borderColor: gridBorder, xaxis: { lines: { show: false } } },
        xaxis: {
            categories: chartData.map((r) => r.label),
            crosshairs: { show: false },
            labels: { show: false },
            axisTicks: { show: false },
            axisBorder: { show: false },
        },
        yaxis: {
            labels: {
                align: 'left',
                style: { colors: yaxisLabelColor, fontSize: '13px' },
            },
        },
        states: { hover: { filter: { type: 'darken', value: 0.9 } as { type?: string; value?: number } } },
        tooltip: {
            custom: (props) =>
                buildTooltip(props as IChartProps, {
                    title: chartData[props.dataPointIndex].label,
                    mode,
                    valuePrefix: '',
                    valuePostfix: props.series[props.seriesIndex][props.dataPointIndex] === 1 ? ' occurrence' : ' occurrences',
                    isValueDivided: false,
                } as IBuildTooltipHelperOptions),
        },
    };

    return (
        <div>
            <ApexChart
                type="bar"
                height={Math.max(120, chartData.length * 40)}
                options={options}
                series={[{ name: 'Occurrences', data: chartData.map((r) => r.occurrences) }]}
            />
            {sorted.length > MAX_CHART_BARS && (
                <p className="mt-1 text-center text-xs text-muted-foreground-2">
                    Showing top {MAX_CHART_BARS} of {sorted.length} labels — see the full table below.
                </p>
            )}
        </div>
    );
}

function ObjectDetectionSection({
    assessment,
    onSeek,
}: {
    assessment: ObjectDetectionAssessment;
    onSeek: (seconds: number) => void;
}) {
    return (
        <div className="flex flex-col gap-3 rounded-lg border border-layer-line p-4">
            <div className="flex flex-wrap items-center gap-2">
                <span className="text-sm font-medium text-foreground">Object detection</span>
                <span className="flex items-center gap-1 text-xs text-muted-foreground-1">
                    {assessment.confidence}% confidence
                    {assessment.timestamp !== null && (
                        <>
                            {' '}
                            · at <TimestampButton seconds={assessment.timestamp} onSeek={onSeek} />
                        </>
                    )}
                </span>
            </div>

            <p className="text-sm text-foreground">{assessment.summary}</p>

            {assessment.objects.length > 0 && (
                <div className="flex flex-wrap gap-2">
                    {assessment.objects.map((object) => (
                        <span
                            key={object.label}
                            className="inline-flex items-center gap-1 rounded-full bg-layer px-2.5 py-1 text-xs font-medium text-foreground"
                        >
                            {object.label}
                            <span className="text-muted-foreground-1">× {object.count}</span>
                        </span>
                    ))}
                </div>
            )}

            {assessment.notable_observations.length > 0 && (
                <ul className="list-disc space-y-1 pl-4 text-sm text-muted-foreground-1">
                    {assessment.notable_observations.map((observation) => (
                        <li key={observation}>{observation}</li>
                    ))}
                </ul>
            )}
        </div>
    );
}

function SuggestionsList({ suggestions }: { suggestions: string[] }) {
    if (suggestions.length === 0) return null;

    return (
        <div className="flex flex-col gap-2 rounded-lg bg-layer p-3">
            <div className="flex items-center gap-1.5 text-xs font-medium text-muted-foreground-1">
                <Lightbulb className="size-3.5" strokeWidth={1.75} />
                Suggestions
            </div>
            <ul className="list-disc space-y-1 pl-4 text-sm text-foreground">
                {suggestions.map((suggestion) => (
                    <li key={suggestion}>{suggestion}</li>
                ))}
            </ul>
        </div>
    );
}

function ThreatAssessmentSection({ assessment, onSeek }: { assessment: ThreatAssessment; onSeek: (seconds: number) => void }) {
    return (
        <div className="flex flex-col gap-3 rounded-lg border border-layer-line p-4">
            <div className="flex flex-wrap items-center gap-2">
                <span className="text-sm font-medium text-foreground">Threat assessment</span>
                <span
                    className={cn(
                        'inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium',
                        RISK_LEVEL_BADGES[assessment.risk_level],
                    )}
                >
                    {assessment.risk_level}
                </span>
                <span className="flex items-center gap-1 text-xs text-muted-foreground-1">
                    {assessment.confidence}% confidence · at <TimestampButton seconds={assessment.timestamp} onSeek={onSeek} />
                </span>
            </div>

            <p className="text-sm text-foreground">{assessment.summary}</p>
            <p className="text-sm text-muted-foreground-1">{assessment.reasoning}</p>

            {assessment.evidence.length > 0 && (
                <div className="max-h-96 overflow-auto rounded-lg border border-card-line">
                    <table className="w-full text-left text-sm">
                        <thead className="sticky top-0 border-b border-card-line bg-card">
                            <tr>
                                <th className="py-2 pr-4 pl-3 font-medium text-muted-foreground-1">Label</th>
                                <th className="py-2 pr-4 font-medium text-muted-foreground-1">Confidence</th>
                                <th className="py-2 pr-3 font-medium text-muted-foreground-1">Timestamp</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-card-line">
                            {assessment.evidence.map((item, index) => (
                                <tr key={`${item.label}-${item.timestamp}-${index}`}>
                                    <td className="py-2 pr-4 pl-3 font-medium text-foreground">{item.label}</td>
                                    <td className="py-2 pr-4 text-muted-foreground-1">{item.rekognition_confidence.toFixed(1)}%</td>
                                    <td className="py-2 pr-3">
                                        <TimestampButton seconds={item.timestamp} onSeek={onSeek} />
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}

            <SuggestionsList suggestions={assessment.suggestions ?? []} />
        </div>
    );
}

function ModerationAssessmentSection({
    assessment,
    onSeek,
}: {
    assessment: ModerationAssessment;
    onSeek: (seconds: number) => void;
}) {
    return (
        <div className="flex flex-col gap-3 rounded-lg border border-layer-line p-4">
            <div className="flex flex-wrap items-center gap-2">
                <span className="text-sm font-medium text-foreground">Content moderation</span>
                <span
                    className={cn(
                        'inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium',
                        MODERATION_STATUS_BADGES[assessment.status],
                    )}
                >
                    {assessment.status}
                </span>
                <span className="flex items-center gap-1 text-xs text-muted-foreground-1">
                    {assessment.severity !== 'NONE' && `${assessment.severity} severity · `}
                    {assessment.confidence}% confidence
                    {assessment.timestamp !== null && (
                        <>
                            {' '}
                            · at <TimestampButton seconds={assessment.timestamp} onSeek={onSeek} />
                        </>
                    )}
                </span>
            </div>

            <p className="text-sm text-foreground">{assessment.summary}</p>
            <p className="text-sm text-muted-foreground-1">{assessment.reasoning}</p>

            <SuggestionsList suggestions={assessment.suggestions ?? []} />
        </div>
    );
}

function InsightsCard({
    insights,
    onSeek,
}: {
    insights: VideoInsightsResponse | null;
    onSeek: (seconds: number) => void;
}) {
    return (
        <Card>
            <CardHeader className="flex-row items-center justify-between gap-4 space-y-0">
                <CardTitle className="text-base">AI insights</CardTitle>
            </CardHeader>
            <CardContent className="flex flex-col gap-4">
                {!insights && (
                    <div className="flex items-center gap-2 text-sm text-muted-foreground-1">
                        <Loader2 className="size-4 animate-spin" strokeWidth={1.75} />
                        AI insights aren't ready yet — this usually takes a few moments. Refresh the page to check.
                    </div>
                )}

                {insights?.object_detection && <ObjectDetectionSection assessment={insights.object_detection} onSeek={onSeek} />}
                {insights?.threat_assessment && <ThreatAssessmentSection assessment={insights.threat_assessment} onSeek={onSeek} />}
                {insights?.moderation && <ModerationAssessmentSection assessment={insights.moderation} onSeek={onSeek} />}
            </CardContent>
        </Card>
    );
}

function InquiryAnswer({ inquiry, onSeek }: { inquiry: VideoInquiry; onSeek: (seconds: number) => void }) {
    const { answer } = inquiry;

    return (
        <div className="flex flex-col gap-2 rounded-lg border border-layer-line p-4">
            <p className="text-sm font-medium text-foreground">{inquiry.question}</p>

            <div className="flex flex-wrap items-center gap-2">
                {!answer.answerable && (
                    <span className="inline-flex items-center rounded-full bg-layer px-2 py-0.5 text-xs font-medium text-muted-foreground-1">
                        Not answerable from available data
                    </span>
                )}
                <span className="text-xs text-muted-foreground-1">{answer.confidence}% confidence</span>
            </div>

            <p className="text-sm text-muted-foreground-1">{answer.answer}</p>

            {answer.evidence.length > 0 && (
                <ul className="flex flex-col gap-1 text-xs text-muted-foreground-1">
                    {answer.evidence.map((item, index) => (
                        <li key={`${item.label}-${item.timestamp}-${index}`} className="flex flex-wrap items-center gap-1">
                            <span className="font-medium text-foreground">{item.label}</span>
                            {item.timestamp !== null && (
                                <>
                                    · at <TimestampButton seconds={item.timestamp} onSeek={onSeek} />
                                </>
                            )}
                            <span>— {item.note}</span>
                        </li>
                    ))}
                </ul>
            )}

            {answer.caveats && <p className="text-xs text-muted-foreground-2 italic">{answer.caveats}</p>}
        </div>
    );
}

function InquireCard({ videoId, onSeek }: { videoId: number; onSeek: (seconds: number) => void }) {
    const [question, setQuestion] = useState('');
    const [history, setHistory] = useState<VideoInquiry[]>([]);
    const [loadingHistory, setLoadingHistory] = useState(true);
    const [asking, setAsking] = useState(false);
    const [error, setError] = useState<string[]>([]);

    useEffect(() => {
        api.get<VideoInquiry[]>(`/api/videos/${videoId}/inquiries`)
            .then((res) => setHistory(res.data))
            .catch(() => setHistory([]))
            .finally(() => setLoadingHistory(false));
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [videoId]);

    async function handleAsk(e: FormEvent) {
        e.preventDefault();
        if (!question.trim()) return;

        setAsking(true);
        setError([]);
        try {
            const res = await api.post<VideoInquiry>(`/api/videos/${videoId}/inquiries`, { question });
            setHistory((current) => [res.data, ...current]);
            setQuestion('');
        } catch (err) {
            setError(getErrorMessages(err));
        } finally {
            setAsking(false);
        }
    }

    return (
        <Card>
            <CardHeader>
                <CardTitle className="text-base">Inquire</CardTitle>
            </CardHeader>
            <CardContent className="flex flex-col gap-4">
                <p className="text-sm text-muted-foreground-1">Ask a specific question about this video's detections.</p>

                <form onSubmit={handleAsk} className="flex gap-2">
                    <Input
                        value={question}
                        onChange={(e) => setQuestion(e.target.value)}
                        placeholder="e.g. Was a weapon visible near the entrance?"
                        disabled={asking}
                        aria-label="Ask a question about this video"
                    />
                    <Button type="submit" disabled={asking || !question.trim()}>
                        {asking ? (
                            <Loader2 className="size-4 animate-spin" strokeWidth={1.75} />
                        ) : (
                            <Sparkles className="size-4" strokeWidth={1.75} />
                        )}
                        Ask
                    </Button>
                </form>

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

                {loadingHistory && (
                    <div className="flex items-center gap-2 text-sm text-muted-foreground-1">
                        <Loader2 className="size-4 animate-spin" strokeWidth={1.75} />
                        Loading history…
                    </div>
                )}

                {!loadingHistory && history.length > 0 && (
                    <div className="flex flex-col gap-3">
                        {history.map((item) => (
                            <InquiryAnswer key={item.id} inquiry={item} onSeek={onSeek} />
                        ))}
                    </div>
                )}
            </CardContent>
        </Card>
    );
}

function AnalysisJobCard({ job, onSeek }: { job: AnalysisJob; onSeek: (seconds: number) => void }) {
    return (
        <Card>
            <CardHeader className="flex-row items-center justify-between gap-4 space-y-0">
                <CardTitle className="text-base">{TYPE_LABELS[job.type] ?? job.type}</CardTitle>
                <JobStatusBadge status={job.status} />
            </CardHeader>
            <CardContent>
                {job.status === 'failed' && (
                    <Alert variant="destructive">
                        <AlertDescription>{job.error_message ?? 'Analysis failed.'}</AlertDescription>
                    </Alert>
                )}

                {(job.status === 'pending' || job.status === 'processing') && (
                    <p className="text-sm text-muted-foreground-1">
                        {job.status === 'pending' ? 'Waiting to start…' : 'Analysis is running…'}
                    </p>
                )}

                {job.status === 'completed' && job.results.length === 0 && (
                    <p className="text-sm text-muted-foreground-1">No labels were detected.</p>
                )}

                {job.status === 'completed' && job.results.length > 1 && (
                    <div className="mb-4">
                        <OccurrencesChart results={job.results} />
                    </div>
                )}

                {job.status === 'completed' && job.results.length > 0 && (
                    <div className="max-h-96 overflow-auto rounded-lg border border-card-line">
                        <table className="w-full text-left text-sm">
                            <thead className="sticky top-0 border-b border-card-line bg-card">
                                <tr>
                                    <th className="py-2 pr-4 pl-3 font-medium text-muted-foreground-1">Label</th>
                                    <th className="py-2 pr-4 font-medium text-muted-foreground-1">Occurrences</th>
                                    <th className="py-2 pr-4 font-medium text-muted-foreground-1">Avg. confidence</th>
                                    <th className="py-2 pr-4 font-medium text-muted-foreground-1">First seen</th>
                                    <th className="py-2 pr-3 font-medium text-muted-foreground-1">Last seen</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-card-line">
                                {job.results
                                    .slice()
                                    .sort((a, b) => b.occurrences - a.occurrences)
                                    .map((result) => (
                                        <tr key={result.id}>
                                            <td className="py-2 pr-4 pl-3 font-medium text-foreground">{result.label}</td>
                                            <td className="py-2 pr-4 text-muted-foreground-1">{result.occurrences}</td>
                                            <td className="py-2 pr-4 text-muted-foreground-1">{result.avg_confidence.toFixed(1)}%</td>
                                            <td className="py-2 pr-4">
                                                <TimestampButton seconds={Number(result.first_seen_at)} onSeek={onSeek} />
                                            </td>
                                            <td className="py-2 pr-3">
                                                <TimestampButton seconds={Number(result.last_seen_at)} onSeek={onSeek} />
                                            </td>
                                        </tr>
                                    ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </CardContent>
        </Card>
    );
}

export default function VideoResults() {
    const { id } = useParams<{ id: string }>();
    const navigate = useNavigate();

    const [video, setVideo] = useState<VideoDetail | null>(null);
    const [loading, setLoading] = useState(true);
    const [loadError, setLoadError] = useState<string[]>([]);

    const [insights, setInsights] = useState<VideoInsightsResponse | null>(null);
    const videoRef = useRef<HTMLVideoElement>(null);

    function seekTo(seconds: number) {
        const el = videoRef.current;
        if (!el) return;
        el.scrollIntoView({ behavior: 'smooth', block: 'center' });
        el.currentTime = seconds;
    }

    useEffect(() => {
        api.get<VideoDetail>(`/api/videos/${id}`)
            .then((res) => {
                setVideo(res.data);
                setInsights(res.data.latest_insight);
            })
            .catch((err) => setLoadError(getErrorMessages(err)))
            .finally(() => setLoading(false));
    }, [id]);

    // Only the latest job per analysis type — an older, superseded attempt from
    // a retry (e.g. after a failure) is dropped, same rule the video's overall
    // status uses.
    const latestJobsByType = Object.values(
        (video?.analysis_jobs ?? []).reduce<Partial<Record<AnalysisJobType, AnalysisJob>>>((latest, job) => {
            const current = latest[job.type];
            if (!current || job.created_at > current.created_at) {
                latest[job.type] = job;
            }
            return latest;
        }, {}),
    ).sort((a, b) => b.created_at.localeCompare(a.created_at));

    return (
        <AppLayout active="videos">
            <Button variant="secondary" onClick={() => navigate('/videos')}>
                <ChevronLeft className="size-4" strokeWidth={1.75} />
                Back to videos
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

            {!loading && video && (
                <>
                    <div className="mt-4 flex items-start justify-between gap-4">
                        <div>
                            <h1 className="font-heading text-2xl font-medium">{video.title}</h1>
                            {video.description && <p className="mt-1 text-sm text-muted-foreground-1">{video.description}</p>}
                        </div>
                        <Button variant="secondary" onClick={() => window.open(`/api/videos/${video.id}/report`, '_blank')}>
                            <Download className="size-4" strokeWidth={1.75} />
                            Download report
                        </Button>
                    </div>

                    <div className="mt-4 grid grid-cols-1 gap-6 lg:grid-cols-3">
                        <video
                            ref={videoRef}
                            controls
                            className="aspect-video w-full rounded-xl bg-black shadow-lg lg:col-span-2"
                            src={`/api/videos/${video.id}/stream`}
                        />

                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">Overview</CardTitle>
                            </CardHeader>
                            <CardContent className="flex flex-col gap-4">
                                <dl className="flex flex-col gap-2 text-sm">
                                    <div className="flex items-center justify-between">
                                        <dt className="text-muted-foreground-1">Status</dt>
                                        <dd className="font-medium capitalize text-foreground">{video.status}</dd>
                                    </div>
                                    <div className="flex items-center justify-between">
                                        <dt className="text-muted-foreground-1">Duration</dt>
                                        <dd className="font-medium text-foreground">{formatDuration(video.duration_seconds)}</dd>
                                    </div>
                                    <div className="flex items-center justify-between">
                                        <dt className="text-muted-foreground-1">Size</dt>
                                        <dd className="font-medium text-foreground">{formatFileSize(video.size)}</dd>
                                    </div>
                                    <div className="flex items-center justify-between">
                                        <dt className="text-muted-foreground-1">Uploaded</dt>
                                        <dd className="font-medium text-foreground">{new Date(video.created_at).toLocaleDateString()}</dd>
                                    </div>
                                </dl>

                                {latestJobsByType.length > 0 && (
                                    <div className="flex flex-col gap-2 border-t border-card-line pt-4">
                                        {latestJobsByType.map((job) => (
                                            <div key={job.id} className="flex items-center justify-between text-sm">
                                                <span className="text-muted-foreground-1">{TYPE_LABELS[job.type] ?? job.type}</span>
                                                <JobStatusBadge status={job.status} />
                                            </div>
                                        ))}
                                    </div>
                                )}
                            </CardContent>
                        </Card>
                    </div>

                    {latestJobsByType.some((job) => job.status === 'completed') && (
                        <div className="mt-6">
                            <InsightsCard insights={insights} onSeek={seekTo} />
                        </div>
                    )}

                    {latestJobsByType.some((job) => job.status === 'completed') && (
                        <div className="mt-6">
                            <InquireCard videoId={video.id} onSeek={seekTo} />
                        </div>
                    )}

                    <div className="mt-6 flex flex-col gap-4">
                        {latestJobsByType.length === 0 && (
                            <p className="text-sm text-muted-foreground-1">This video has no analysis jobs yet.</p>
                        )}
                        {latestJobsByType.map((job) => (
                            <AnalysisJobCard key={job.id} job={job} onSeek={seekTo} />
                        ))}
                    </div>
                </>
            )}
        </AppLayout>
    );
}
