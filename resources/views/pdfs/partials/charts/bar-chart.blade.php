{{-- Params: $id, $categories (array<string>), $data (array<int>), $seriesName, $horizontal (optional bool), $height (optional, default 260) --}}
<div id="{{ $id }}"></div>
<script>
    window.__pdfCharts.push(
        new ApexCharts(document.querySelector('#{{ $id }}'), {
            chart: { type: 'bar', height: {{ $height ?? 260 }}, fontFamily: 'inherit', foreColor: '#71717a', toolbar: { show: false }, animations: { enabled: false } },
            colors: ['#0891b2'],
            plotOptions: { bar: @if($horizontal ?? false) { horizontal: true, borderRadius: 6 } @else { borderRadius: 6, columnWidth: '55%' } @endif },
            dataLabels: { enabled: false },
            grid: { borderColor: '#e4e4e7', strokeDashArray: 4 },
            xaxis: { categories: {!! json_encode($categories) !!} },
            series: [{ name: {!! json_encode($seriesName) !!}, data: {!! json_encode($data) !!} }],
        }).render()
    );
</script>
