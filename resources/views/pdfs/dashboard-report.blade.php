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
    <title>Dashboard report</title>
    @include('pdfs.partials.base-styles')
    @include('pdfs.partials.apexcharts-lib')
</head>
<body>
    <div class="header">
        <div>
            <h1>Dashboard report</h1>
            <p class="muted">Across all your workspaces</p>
        </div>
        <div style="text-align: right;">
            <p class="muted">Generated {{ $generatedAt->format('M j, Y g:i A') }}</p>
        </div>
    </div>

    <h2>Overview</h2>
    <table class="overview-table">
        <tr><td>Workspaces</td><td>{{ $dashboard['stats']['total_workspaces'] }}</td></tr>
        <tr><td>Total videos</td><td>{{ $dashboard['stats']['total_videos'] }}</td></tr>
        <tr><td>Storage used</td><td>{{ \App\Support\ReportFormatter::fileSize($dashboard['stats']['total_storage_bytes']) }}</td></tr>
        <tr><td>Processing now</td><td>{{ $dashboard['stats']['processing_videos'] }}</td></tr>
        <tr><td>Stuck processing (30+ min)</td><td>{{ $dashboard['stats']['stuck_processing_videos'] }}</td></tr>
        <tr><td>Failed videos</td><td>{{ $dashboard['stats']['failed_videos'] }}</td></tr>
        <tr><td>Inquiries asked</td><td>{{ $dashboard['stats']['total_inquiries'] }}</td></tr>
        <tr><td>Cameras</td><td>{{ $dashboard['stats']['total_cameras'] }}</td></tr>
        <tr><td>Recording now</td><td>{{ $dashboard['stats']['active_recordings'] }}</td></tr>
        <tr><td>Needs review</td><td>{{ $dashboard['stats']['flagged_for_review'] }}</td></tr>
    </table>

    <h2>Safety spotlight</h2>
    <p class="muted">
        {{ $dashboard['safety_spotlight']['threats_detected'] }} threat(s) ·
        {{ $dashboard['safety_spotlight']['flagged_moderation'] }} moderation flag(s), across all workspaces
    </p>
    @if (! empty($dashboard['safety_spotlight']['items']))
        <table>
            <thead>
                <tr><th>Video</th><th>Workspace</th><th>Type</th><th>Severity</th></tr>
            </thead>
            <tbody>
                @foreach ($dashboard['safety_spotlight']['items'] as $item)
                    <tr>
                        <td>{{ $item['video_title'] }}</td>
                        <td>{{ $item['workspace_name'] }}</td>
                        <td>{{ $item['type'] === 'threat' ? 'Threat detected' : 'Moderation flag' }}</td>
                        <td>{{ $item['severity'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <p class="muted">No threats or moderation flags right now.</p>
    @endif

    <h2>Needs review</h2>
    @if (! empty($dashboard['needs_review']))
        <table>
            <thead>
                <tr><th>Video</th><th>Workspace</th><th>Type</th><th>Flagged by</th><th>Note</th></tr>
            </thead>
            <tbody>
                @foreach ($dashboard['needs_review'] as $item)
                    <tr>
                        <td>{{ $item['video_title'] }}</td>
                        <td>{{ $item['workspace_name'] }}</td>
                        <td>{{ $typeLabels[$item['type']] ?? $item['type'] }}</td>
                        <td>{{ $item['flagged_by'] ?? '—' }}</td>
                        <td>{{ $item['note'] ?? '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <p class="muted">No flagged results right now.</p>
    @endif

    <h2>Workspaces</h2>
    @if (! empty($dashboard['workspace_leaderboard']))
        <table>
            <thead>
                <tr><th>Name</th><th>Videos</th><th>Failed</th><th>Flagged</th><th>Cameras</th><th>Last activity</th></tr>
            </thead>
            <tbody>
                @foreach ($dashboard['workspace_leaderboard'] as $workspace)
                    <tr>
                        <td>{{ $workspace['name'] }}</td>
                        <td>{{ $workspace['total_videos'] }}</td>
                        <td>{{ $workspace['failed_videos'] }}</td>
                        <td>{{ $workspace['flagged_count'] }}</td>
                        <td>{{ $workspace['total_cameras'] }}</td>
                        <td>{{ $workspace['last_activity_at']?->format('M j, Y') ?? '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <h2>Recent activity</h2>
    @if (! empty($dashboard['recent_activity']))
        <table>
            <thead>
                <tr><th>Title</th><th>Workspace</th><th>Status</th><th>Uploaded</th></tr>
            </thead>
            <tbody>
                @foreach ($dashboard['recent_activity'] as $video)
                    <tr>
                        <td>{{ $video['title'] }}</td>
                        <td>{{ $video['workspace_name'] }}</td>
                        <td>{{ ucfirst($video['status']) }}</td>
                        <td>{{ $video['created_at']->format('M j, Y') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <p class="muted">No videos uploaded yet.</p>
    @endif

    <h2>Top detected labels</h2>
    @if (! empty($dashboard['top_labels']))
        @include('pdfs.partials.bar-rows', ['rows' => collect($dashboard['top_labels'])->map(fn ($l) => ['label' => $l['label'], 'value' => $l['occurrences']])])
    @else
        <p class="muted">No detections yet.</p>
    @endif

    <h2>Upload trend (last 14 days)</h2>
    @include('pdfs.partials.charts.area-chart', [
        'id' => 'chart-uploads',
        'categories' => collect($dashboard['uploads_over_time'])->map(fn ($d) => \Carbon\Carbon::parse($d['date'])->format('M j')),
        'data' => collect($dashboard['uploads_over_time'])->pluck('count'),
        'seriesName' => 'Uploaded',
    ])

    <div class="footer">
        Generated by 7Sight — {{ config('app.url') }}
    </div>

    @include('pdfs.partials.pdf-ready')
</body>
</html>
