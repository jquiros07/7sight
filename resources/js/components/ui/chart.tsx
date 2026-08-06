import { useEffect, useRef } from 'react';
import ApexCharts, { type ApexOptions } from 'apexcharts';

interface ApexChartProps {
    type: NonNullable<ApexOptions['chart']>['type'];
    height?: number | string;
    options: ApexOptions;
    series: ApexOptions['series'];
}

export function ApexChart({ type, height = 300, options, series }: ApexChartProps) {
    const elRef = useRef<HTMLDivElement>(null);

    useEffect(() => {
        if (!elRef.current) return;

        const chart = new ApexCharts(elRef.current, {
            ...options,
            chart: {
                ...options.chart,
                type,
                height,
                toolbar: { show: false, ...options.chart?.toolbar },
            },
            series,
        });

        chart.render();

        return () => {
            chart.destroy();
        };
        // Rendered once with the data it was mounted with — this wraps static/mocked charts, not live-updating ones.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    return <div ref={elRef} />;
}