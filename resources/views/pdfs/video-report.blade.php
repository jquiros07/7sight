@php
    $typeLabels = [
        'object_detection' => 'Object detection',
        'threat_detection' => 'Threat detection',
        'content_moderation' => 'Content moderation',
        'ai_generated' => 'AI generated',
    ];

    $riskLevelColors = [
        'LOW' => '#166534',
        'MEDIUM' => '#92400e',
        'HIGH' => '#9a3412',
        'CRITICAL' => '#991b1b',
    ];

    $moderationStatusColors = [
        'SAFE' => '#166534',
        'REVIEW' => '#92400e',
        'UNSAFE' => '#991b1b',
    ];
@endphp
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $video->title }} — Analysis report</title>
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: 'DejaVu Sans', Helvetica, Arial, sans-serif;
            font-size: 11px;
            color: #18181b;
            margin: 0;
        }
        h1, h2, h3 { margin: 0 0 6px; font-weight: 600; }
        h1 { font-size: 20px; }
        h2 { font-size: 14px; margin-top: 20px; padding-bottom: 4px; border-bottom: 1px solid #e4e4e7; }
        h3 { font-size: 12px; }
        p { margin: 4px 0; }
        .muted { color: #71717a; }
        .header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 12px; }
        .badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 999px;
            font-size: 10px;
            font-weight: 600;
            color: #fff;
            background: #52525b;
        }
        .section { margin-top: 10px; }
        table { width: 100%; border-collapse: collapse; margin-top: 6px; }
        th, td { text-align: left; padding: 4px 6px; font-size: 10px; }
        th { color: #71717a; border-bottom: 1px solid #d4d4d8; font-weight: 600; }
        td { border-bottom: 1px solid #f4f4f5; }
        .overview-table td { border-bottom: none; padding: 2px 6px 2px 0; }
        .overview-table td:first-child { color: #71717a; width: 110px; }
        .job-block { margin-top: 14px; padding: 10px; border: 1px solid #e4e4e7; border-radius: 6px; page-break-inside: avoid; }
        .bar-chart { margin-top: 8px; }
        .bar-row { display: flex; align-items: center; margin-bottom: 5px; }
        .bar-label { width: 130px; font-size: 10px; color: #3f3f46; padding-right: 8px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .bar-track { flex: 1; background: #f4f4f5; border-radius: 4px; height: 14px; overflow: hidden; }
        .bar-fill { background: #0891b2; height: 100%; border-radius: 4px; }
        .bar-value { width: 30px; text-align: right; font-size: 10px; color: #71717a; padding-left: 8px; }
        .insight-block { margin-top: 10px; padding: 10px; border: 1px solid #e4e4e7; border-radius: 6px; }
        .suggestions { margin-top: 8px; padding: 8px; background: #f4f4f5; border-radius: 6px; }
        .suggestions ul, .observations ul { margin: 4px 0; padding-left: 16px; }
        .footer { margin-top: 24px; padding-top: 8px; border-top: 1px solid #e4e4e7; font-size: 9px; color: #a1a1aa; }
    </style>
</head>
<body>
    <div class="header">
        <div>
            <h1>{{ $video->title }}</h1>
            @if ($video->description)
                <p class="muted">{{ $video->description }}</p>
            @endif
        </div>
        <div style="text-align: right;">
            <p class="muted">Analysis report</p>
            <p class="muted">Generated {{ $generatedAt->format('M j, Y g:i A') }}</p>
        </div>
    </div>

    <h2>Overview</h2>
    <table class="overview-table">
        <tr><td>Workspace</td><td>{{ $video->workspace->name }}</td></tr>
        <tr><td>Status</td><td>{{ ucfirst($video->status->value) }}</td></tr>
        <tr><td>Duration</td><td>{{ \App\Support\ReportFormatter::duration($video->duration_seconds) }}</td></tr>
        <tr><td>Size</td><td>{{ \App\Support\ReportFormatter::fileSize($video->size) }}</td></tr>
        <tr><td>Uploaded</td><td>{{ $video->created_at->format('M j, Y') }}</td></tr>
    </table>

    @if ($insights)
        <h2>AI insights</h2>

        @if ($insights->object_detection)
            @php
                $assessment = $insights->object_detection;
            @endphp
            <div class="insight-block">
                <h3>Object detection</h3>
                <p class="muted">
                    {{ $assessment['confidence'] }}% confidence
                    @if ($assessment['timestamp'] !== null)
                        · at {{ \App\Support\ReportFormatter::seconds($assessment['timestamp']) }}
                    @endif
                </p>
                <p>{{ $assessment['summary'] }}</p>
                @if (! empty($assessment['objects']))
                    <p class="muted">
                        {{ collect($assessment['objects'])->map(fn ($o) => "{$o['label']} × {$o['count']}")->join(', ') }}
                    </p>
                @endif
                @if (! empty($assessment['notable_observations']))
                    <div class="observations">
                        <ul>
                            @foreach ($assessment['notable_observations'] as $observation)
                                <li>{{ $observation }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>
        @endif

        @if ($insights->threat_assessment)
            @php
                $assessment = $insights->threat_assessment;
            @endphp
            <div class="insight-block">
                <h3>
                    Threat assessment
                    <span class="badge" style="background: {{ $riskLevelColors[$assessment['risk_level']] ?? '#52525b' }}">
                        {{ $assessment['risk_level'] }}
                    </span>
                </h3>
                <p class="muted">{{ $assessment['confidence'] }}% confidence · at {{ \App\Support\ReportFormatter::seconds($assessment['timestamp']) }}</p>
                <p>{{ $assessment['summary'] }}</p>
                <p class="muted">{{ $assessment['reasoning'] }}</p>

                @if (! empty($assessment['evidence']))
                    <table>
                        <thead>
                            <tr><th>Label</th><th>Confidence</th><th>Timestamp</th></tr>
                        </thead>
                        <tbody>
                            @foreach ($assessment['evidence'] as $item)
                                <tr>
                                    <td>{{ $item['label'] }}</td>
                                    <td>{{ number_format($item['rekognition_confidence'], 1) }}%</td>
                                    <td>{{ \App\Support\ReportFormatter::seconds($item['timestamp']) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif

                @if (! empty($assessment['suggestions']))
                    <div class="suggestions">
                        <strong>Suggestions</strong>
                        <ul>
                            @foreach ($assessment['suggestions'] as $suggestion)
                                <li>{{ $suggestion }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>
        @endif

        @if ($insights->moderation)
            @php
                $assessment = $insights->moderation;
            @endphp
            <div class="insight-block">
                <h3>
                    Content moderation
                    <span class="badge" style="background: {{ $moderationStatusColors[$assessment['status']] ?? '#52525b' }}">
                        {{ $assessment['status'] }}
                    </span>
                </h3>
                <p class="muted">
                    @if ($assessment['severity'] !== 'NONE')
                        {{ $assessment['severity'] }} severity ·
                    @endif
                    {{ $assessment['confidence'] }}% confidence
                    @if ($assessment['timestamp'] !== null)
                        · at {{ \App\Support\ReportFormatter::seconds($assessment['timestamp']) }}
                    @endif
                </p>
                <p>{{ $assessment['summary'] }}</p>
                <p class="muted">{{ $assessment['reasoning'] }}</p>

                @if (! empty($assessment['suggestions']))
                    <div class="suggestions">
                        <strong>Suggestions</strong>
                        <ul>
                            @foreach ($assessment['suggestions'] as $suggestion)
                                <li>{{ $suggestion }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>
        @endif
    @endif

    <h2>Analysis results</h2>
    @forelse ($jobs as $job)
        <div class="job-block">
            <h3>
                {{ $typeLabels[$job->type->value] ?? $job->type->value }}
                <span class="muted" style="font-weight: 400;">— {{ ucfirst($job->status) }}</span>
            </h3>

            @if ($job->status === 'failed')
                <p class="muted">{{ $job->error_message ?? 'Analysis failed.' }}</p>
            @elseif (in_array($job->status, ['pending', 'processing'], true))
                <p class="muted">{{ $job->status === 'pending' ? 'Waiting to start…' : 'Analysis is running…' }}</p>
            @elseif ($job->results->isEmpty())
                <p class="muted">No labels were detected.</p>
            @else
                @if ($job->results->count() > 1)
                    @php
                        $chartResults = $job->results->sortByDesc('occurrences')->take(12)->values();
                        $maxOccurrences = $chartResults->max('occurrences');
                    @endphp
                    <div class="bar-chart">
                        @foreach ($chartResults as $result)
                            <div class="bar-row">
                                <div class="bar-label">{{ $result->label }}</div>
                                <div class="bar-track">
                                    <div class="bar-fill" style="width: {{ $maxOccurrences > 0 ? round($result->occurrences / $maxOccurrences * 100, 1) : 0 }}%;"></div>
                                </div>
                                <div class="bar-value">{{ $result->occurrences }}</div>
                            </div>
                        @endforeach
                    </div>
                @endif
                <table>
                    <thead>
                        <tr>
                            <th>Label</th>
                            <th>Occurrences</th>
                            <th>Avg. confidence</th>
                            <th>First seen</th>
                            <th>Last seen</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($job->results->sortByDesc('occurrences') as $result)
                            <tr>
                                <td>{{ $result->label }}</td>
                                <td>{{ $result->occurrences }}</td>
                                <td>{{ number_format($result->avg_confidence, 1) }}%</td>
                                <td>{{ \App\Support\ReportFormatter::seconds($result->first_seen_at) }}</td>
                                <td>{{ \App\Support\ReportFormatter::seconds($result->last_seen_at) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    @empty
        <p class="muted">This video has no analysis jobs yet.</p>
    @endforelse

    <div class="footer">
        Generated by 7Sight — {{ config('app.url') }}
    </div>
</body>
</html>
