{{-- Params: $id, $categories (array), $data (array<int>), $seriesName, $height (optional, default 220) --}}
<div id="{{ $id }}"></div>
<script>
    window.__pdfCharts.push(
        new ApexCharts(document.querySelector('#{{ $id }}'), {
            chart: { type: 'area', height: {{ $height ?? 220 }}, fontFamily: 'inherit', foreColor: '#71717a', toolbar: { show: false }, animations: { enabled: false } },
            colors: ['#0891b2'],
            stroke: { curve: 'smooth', width: 2 },
            fill: { type: 'gradient', gradient: { opacityFrom: 0.35, opacityTo: 0.05 } },
            dataLabels: { enabled: false },
            grid: { borderColor: '#e4e4e7', strokeDashArray: 4 },
            xaxis: { categories: {!! json_encode($categories) !!}, labels: { rotate: 0 }, tickAmount: 6 },
            yaxis: { labels: { formatter: function (v) { return Math.round(v); } } },
            series: [{ name: {!! json_encode($seriesName) !!}, data: {!! json_encode($data) !!} }],
        }).render()
    );
</script>
