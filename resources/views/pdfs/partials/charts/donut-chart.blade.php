{{-- Params: $id, $labels (array<string>), $colors (array<string>), $series (array<int>), $totalLabel, $height (optional, default 260) --}}
<div id="{{ $id }}"></div>
<script>
    window.__pdfCharts.push(
        new ApexCharts(document.querySelector('#{{ $id }}'), {
            chart: { type: 'donut', height: {{ $height ?? 260 }}, fontFamily: 'inherit', foreColor: '#71717a', animations: { enabled: false } },
            labels: {!! json_encode($labels) !!},
            colors: {!! json_encode($colors) !!},
            legend: { position: 'bottom' },
            dataLabels: { enabled: true, formatter: function (v) { return Math.round(v) + '%'; } },
            plotOptions: { pie: { donut: { labels: { show: true, total: { show: true, label: {!! json_encode($totalLabel) !!} } } } } },
            series: {!! json_encode($series) !!},
        }).render()
    );
</script>
