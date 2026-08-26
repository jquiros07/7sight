@php
    $maxValue = collect($rows)->max('value') ?: 0;
@endphp
<div class="bar-chart">
    @foreach ($rows as $row)
        <div class="bar-row">
            <div class="bar-label">{{ $row['label'] }}</div>
            <div class="bar-track">
                <div class="bar-fill" style="width: {{ $maxValue > 0 ? round($row['value'] / $maxValue * 100, 1) : 0 }}%;"></div>
            </div>
            <div class="bar-value">{{ $row['value'] }}</div>
        </div>
    @endforeach
</div>
