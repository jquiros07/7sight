import { FormEvent, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { AppLayout } from '@/components/AppLayout';
import { Loader2, Search as SearchIcon, SearchX, Sparkles } from 'lucide-react';
import { api } from '../lib/api';
import { getErrorMessages } from '../lib/errors';
import { cn } from '../lib/utils';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';

type VideoStatus = 'uploaded' | 'processing' | 'ready' | 'failed';

type Relevance = 'HIGH' | 'MEDIUM' | 'LOW';

type SearchMatch = {
    video_id: number;
    title: string;
    workspace_name: string;
    status: VideoStatus;
    relevance: Relevance;
    reason: string;
};

type SearchResponse = {
    query: string;
    candidates_searched: number;
    matches: SearchMatch[];
};

const STATUS_STYLES: Record<VideoStatus, { label: string; className: string }> = {
    uploaded: { label: 'Uploaded', className: 'bg-violet-100 text-violet-800 dark:bg-violet-500/20 dark:text-violet-400' },
    processing: { label: 'Processing', className: 'bg-amber-100 text-amber-800 dark:bg-amber-500/20 dark:text-amber-400' },
    ready: { label: 'Analyzed', className: 'bg-green-100 text-green-800 dark:bg-green-500/20 dark:text-green-400' },
    failed: { label: 'Failed', className: 'bg-red-100 text-red-800 dark:bg-red-500/20 dark:text-red-400' },
};

const RELEVANCE_STYLES: Record<Relevance, string> = {
    HIGH: 'bg-primary/10 text-primary',
    MEDIUM: 'bg-amber-100 text-amber-800 dark:bg-amber-500/20 dark:text-amber-400',
    LOW: 'bg-slate-100 text-slate-800 dark:bg-slate-500/20 dark:text-slate-400',
};

const EXAMPLE_QUERIES = ['violence with weapons', 'crowded scenes with vehicles', 'content flagged as unsafe'];

function MatchCard({ match, onClick }: { match: SearchMatch; onClick: () => void }) {
    const status = STATUS_STYLES[match.status];

    return (
        <button onClick={onClick} className="flex w-full flex-col gap-2 rounded-lg border border-card-line p-4 text-left hover:bg-layer">
            <div className="flex items-start justify-between gap-3">
                <div className="min-w-0">
                    <p className="truncate text-sm font-medium text-foreground">{match.title}</p>
                    <p className="truncate text-xs text-muted-foreground-1">{match.workspace_name}</p>
                </div>
                <div className="flex shrink-0 items-center gap-1.5">
                    <span className={cn('rounded-full px-2 py-0.5 text-xs font-medium', RELEVANCE_STYLES[match.relevance])}>
                        {match.relevance}
                    </span>
                    <span className={cn('rounded-full px-2 py-0.5 text-xs font-medium', status.className)}>{status.label}</span>
                </div>
            </div>
            <p className="text-sm text-muted-foreground-1">{match.reason}</p>
        </button>
    );
}

export default function VideoSearch() {
    const navigate = useNavigate();

    const [query, setQuery] = useState('');
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState<string[]>([]);
    const [result, setResult] = useState<SearchResponse | null>(null);

    function runSearch(searchQuery: string) {
        if (!searchQuery.trim()) return;

        setLoading(true);
        setError([]);
        api.post<SearchResponse>('/api/search/videos', { query: searchQuery })
            .then((res) => setResult(res.data))
            .catch((err) => {
                setError(getErrorMessages(err));
                setResult(null);
            })
            .finally(() => setLoading(false));
    }

    function handleSubmit(e: FormEvent) {
        e.preventDefault();
        runSearch(query);
    }

    function handleExampleClick(example: string) {
        setQuery(example);
        runSearch(example);
    }

    return (
        <AppLayout active="search">
            <h1 className="font-heading text-2xl font-medium">Search</h1>
            <p className="mt-1 text-sm text-muted-foreground-1">
                Describe what you're looking for in plain language, and AI will search your analyzed videos' detected content for matches.
            </p>

            <Card className="mt-6 p-4">
                <form onSubmit={handleSubmit} className="flex flex-col gap-3 sm:flex-row">
                    <div className="relative flex-1">
                        <SearchIcon className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground-2" strokeWidth={1.75} />
                        <Input
                            value={query}
                            onChange={(e) => setQuery(e.target.value)}
                            placeholder="e.g. violence with weapons"
                            className="pl-9"
                        />
                    </div>
                    <Button type="submit" disabled={loading || query.trim().length < 3}>
                        {loading ? <Loader2 className="size-4 animate-spin" strokeWidth={1.75} /> : <Sparkles className="size-4" strokeWidth={1.75} />}
                        Search
                    </Button>
                </form>

                {!result && !loading && (
                    <div className="mt-3 flex flex-wrap items-center gap-2">
                        <span className="text-xs text-muted-foreground-2">Try:</span>
                        {EXAMPLE_QUERIES.map((example) => (
                            <button
                                key={example}
                                type="button"
                                onClick={() => handleExampleClick(example)}
                                className="rounded-full border border-card-line px-2.5 py-1 text-xs text-muted-foreground-1 hover:bg-layer"
                            >
                                {example}
                            </button>
                        ))}
                    </div>
                )}
            </Card>

            {error.length > 0 && (
                <Alert variant="destructive" className="mt-4" onDismiss={() => setError([])}>
                    <AlertDescription>
                        <ul className="list-disc space-y-1 pl-4">
                            {error.map((message) => (
                                <li key={message}>{message}</li>
                            ))}
                        </ul>
                    </AlertDescription>
                </Alert>
            )}

            {loading && (
                <div className="mt-10 flex flex-col items-center gap-2 text-center">
                    <Loader2 className="size-6 animate-spin text-primary" strokeWidth={1.75} />
                    <p className="text-sm text-muted-foreground-1">Searching your analyzed videos…</p>
                </div>
            )}

            {!loading && result && (
                <div className="mt-6">
                    <p className="mb-3 text-sm text-muted-foreground-1">
                        {result.candidates_searched === 0
                            ? "You don't have any analyzed videos yet — generate insights for a video first."
                            : `Searched ${result.candidates_searched} analyzed video${result.candidates_searched === 1 ? '' : 's'} for "${result.query}".`}
                    </p>

                    {result.candidates_searched > 0 && result.matches.length === 0 && (
                        <div className="flex flex-col items-center gap-2 py-10 text-center">
                            <SearchX className="size-6 text-muted-foreground-2" strokeWidth={1.75} />
                            <p className="text-sm text-muted-foreground-1">No matches for this search.</p>
                        </div>
                    )}

                    <div className="flex flex-col gap-3">
                        {result.matches.map((match) => (
                            <MatchCard key={match.video_id} match={match} onClick={() => navigate(`/videos/${match.video_id}/results`)} />
                        ))}
                    </div>
                </div>
            )}
        </AppLayout>
    );
}
