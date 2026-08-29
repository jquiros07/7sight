@php
    $typeLabels = [
        'object_detection' => 'Object detection',
        'threat_detection' => 'Threat detection',
        'content_moderation' => 'Content moderation',
        'text_detection' => 'Text detection',
    ];
@endphp
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $dashboard['workspace']['name'] }} — Dashboard report</title>
    @include('pdfs.partials.base-styles')
    @include('pdfs.partials.apexcharts-lib')
</head>
<body>
    <div class="header">
        <div>
            <h1>{{ $dashboard['workspace']['name'] }}</h1>
            <p class="muted">Dashboard report</p>
        </div>
        <div style="text-align: right;">
            <p class="muted">Generated {{ $generatedAt->format('M j, Y g:i A') }}</p>
        </div>
    </div>

    @if ($dashboard['insight_summary'])
        <h2>Workspace summary</h2>
        <p>{{ $dashboard['insight_summary']['summary'] }}</p>
        @if (! empty($dashboard['insight_summary']['highlights']))
            <ul>
                @foreach ($dashboard['insight_summary']['highlights'] as $highlight)
                    <li>{{ $highlight }}</li>
                @endforeach
            </ul>
        @endif
        <p class="muted">Generated {{ $dashboard['insight_summary']['generated_at']->format('M j, Y g:i A') }}</p>
    @endif

    {{-- Same 4 stats, same order, as the page's first stat row. --}}
    <h2>Overview</h2>
    <table class="overview-table">
        <tr><td>Total videos</td><td>{{ $dashboard['stats']['total_videos'] }}</td></tr>
        <tr><td>Storage used</td><td>{{ \App\Support\ReportFormatter::fileSize($dashboard['stats']['total_storage_bytes']) }}</td></tr>
        <tr><td>Completed analyses</td><td>{{ $dashboard['stats']['completed_analyses'] }}</td></tr>
        <tr><td>Processing now</td><td>{{ $dashboard['stats']['processing_now'] }}</td></tr>
    </table>

    {{-- Same 4 stats, same order, as the page's second stat row (Threats/Moderation/
         AI-content/Needs review all sit together there). --}}
    <h2>Safety</h2>
    <table class="overview-table">
        <tr><td>Threats detected</td><td>{{ $dashboard['insight_flags']['threats_detected'] }}</td></tr>
        <tr><td>Flagged for moderation</td><td>{{ $dashboard['insight_flags']['flagged_moderation'] }}</td></tr>
        <tr><td>AI-generated content flagged</td><td>{{ $dashboard['insight_flags']['ai_generated_content_flagged'] }}</td></tr>
        <tr><td>Needs review</td><td>{{ $dashboard['stats']['flagged_for_review'] }}</td></tr>
    </table>

    <h2>Needs review</h2>
    @if (! empty($dashboard['needs_review']))
        <table>
            <thead>
                <tr><th>Type</th><th>Flagged by</th><th>Flagged at</th><th>Note</th></tr>
            </thead>
            <tbody>
                @foreach ($dashboard['needs_review'] as $item)
                    <tr>
                        <td>{{ $typeLabels[$item['type']] ?? $item['type'] }}</td>
                        <td>{{ $item['flagged_by'] ?? '—' }}</td>
                        <td>{{ $item['flagged_at']->format('M j, Y g:i A') }}</td>
                        <td>{{ $item['note'] ?? '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <p class="muted">No flagged results right now.</p>
    @endif

    {{-- Comes after Needs review here because that's where the page's third stat
         row (Failed jobs/Avg. processing time/Tool jobs run) actually sits - below
         the Needs Review card, not merged into Overview above. --}}
    <h2>Job &amp; tool stats</h2>
    <table class="overview-table">
        <tr><td>Failed jobs</td><td>{{ $dashboard['stats']['failed_jobs'] }}</td></tr>
        <tr><td>Avg. processing time</td><td>{{ \App\Support\ReportFormatter::duration($dashboard['stats']['avg_processing_seconds']) }}</td></tr>
        <tr><td>Tool jobs run</td><td>{{ $dashboard['stats']['tool_jobs_run'] }}</td></tr>
    </table>

    {{-- Same order as the page: Uploads and Videos by status sit side by side there. --}}
    <h2>Upload trend (last 14 days)</h2>
    @include('pdfs.partials.charts.area-chart', [
        'id' => 'chart-uploads',
        'categories' => collect($dashboard['uploads_over_time'])->map(fn ($d) => \Carbon\Carbon::parse($d['date'])->format('M j')),
        'data' => collect($dashboard['uploads_over_time'])->pluck('count'),
        'seriesName' => 'Uploaded',
    ])

    <h2>Videos by status</h2>
    @include('pdfs.partials.charts.donut-chart', [
        'id' => 'chart-videos-by-status',
        'labels' => ['Uploaded', 'Processing', 'Ready', 'Failed'],
        'colors' => ['#0891b2', '#f59e0b', '#22c55e', '#ef4444'],
        'series' => [
            $dashboard['videos_by_status']['uploaded'],
            $dashboard['videos_by_status']['processing'],
            $dashboard['videos_by_status']['ready'],
            $dashboard['videos_by_status']['failed'],
        ],
        'totalLabel' => 'Videos',
    ])

    <h2>Analysis jobs by type</h2>
    @include('pdfs.partials.charts.bar-chart', [
        'id' => 'chart-jobs-by-type',
        'categories' => ['Object detection', 'Threat detection', 'Content moderation', 'Text detection'],
        'data' => [
            $dashboard['jobs_by_type']['object_detection'],
            $dashboard['jobs_by_type']['threat_detection'],
            $dashboard['jobs_by_type']['content_moderation'],
            $dashboard['jobs_by_type']['text_detection'],
        ],
        'seriesName' => 'Jobs',
    ])

    <h2>Top detected labels</h2>
    @if (! empty($dashboard['top_labels']))
        @include('pdfs.partials.charts.bar-chart', [
            'id' => 'chart-top-labels',
            'categories' => collect($dashboard['top_labels'])->pluck('label'),
            'data' => collect($dashboard['top_labels'])->pluck('occurrences'),
            'seriesName' => 'Occurrences',
            'horizontal' => true,
        ])
    @else
        <p class="muted">No detections yet.</p>
    @endif

    <h2>Risk level breakdown</h2>
    @if (array_sum($dashboard['risk_level_breakdown']) > 0)
        @include('pdfs.partials.charts.donut-chart', [
            'id' => 'chart-risk-level',
            'labels' => ['Low', 'Medium', 'High', 'Critical'],
            'colors' => ['#22c55e', '#f59e0b', '#f97316', '#ef4444'],
            'series' => [
                $dashboard['risk_level_breakdown']['LOW'],
                $dashboard['risk_level_breakdown']['MEDIUM'],
                $dashboard['risk_level_breakdown']['HIGH'],
                $dashboard['risk_level_breakdown']['CRITICAL'],
            ],
            'totalLabel' => 'Assessed',
        ])
    @else
        <p class="muted">No threat assessments yet.</p>
    @endif

    <h2>Moderation severity breakdown</h2>
    @if (array_sum($dashboard['moderation_severity_breakdown']) > 0)
        @include('pdfs.partials.charts.donut-chart', [
            'id' => 'chart-moderation-severity',
            'labels' => ['None', 'Low', 'Medium', 'High'],
            'colors' => ['#22c55e', '#0891b2', '#f59e0b', '#ef4444'],
            'series' => [
                $dashboard['moderation_severity_breakdown']['NONE'],
                $dashboard['moderation_severity_breakdown']['LOW'],
                $dashboard['moderation_severity_breakdown']['MEDIUM'],
                $dashboard['moderation_severity_breakdown']['HIGH'],
            ],
            'totalLabel' => 'Assessed',
        ])
    @else
        <p class="muted">No moderation assessments yet.</p>
    @endif

    <div class="footer">
        Generated by 7Sight — {{ config('app.url') }}
    </div>

    @include('pdfs.partials.pdf-ready')
</body>
</html>
