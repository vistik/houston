import { ChartPanel, MetricTile } from '@/components/report/widgets';
import { formatNumber } from '@/lib/format';
import type { PullRequestReport } from '@/types';

/**
 * How many people opened, merged or reviewed a pull request, in the period and per trend bucket, as
 * grid cells for a report section.
 */
export function ActiveDevelopers({
    report,
    labels,
    tooltipLabels,
    dates,
}: {
    report: PullRequestReport;
    labels: string[];
    tooltipLabels: string[];
    dates: string[];
}) {
    return (
        <>
            <MetricTile
                size="lg"
                label="Active developers"
                hint="Opened, merged or reviewed a PR"
                metric={report.kpis.activeDevelopers}
            />
            <ChartPanel
                span="3/4"
                title="Active developers over time"
                metric={report.kpis.activeDevelopers}
                description={`Per ${report.period.granularity}`}
                chart={{
                    kind: 'bar',
                    height: 150,
                    labels,
                    tooltipLabels,
                    dates,
                    series: [
                        {
                            key: 'activeDevelopers',
                            label: 'Active developers',
                            color: 'var(--series-1)',
                            values: report.series.activeDevelopers ?? [],
                        },
                    ],
                    format: (value) => formatNumber(value),
                }}
            />
        </>
    );
}
