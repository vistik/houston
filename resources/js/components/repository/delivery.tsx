import type { TimeSeriesChartProps } from '@/components/charts/time-series-chart';
import { AlertIcon } from '@/components/icons';
import { BenchmarkBadge } from '@/components/metric-card';
import { ChartPanel, MetricTile, PanelCell } from '@/components/report/widgets';
import { Badge } from '@/components/ui/badge';
import { useTimeZone } from '@/hooks/use-time-zone';
import { bucketThresholds } from '@/lib/benchmarks';
import {
    formatDate,
    formatDuration,
    formatNumber,
    timeAgo,
} from '@/lib/format';
import type { Metric, MetricKey, VelocityReport } from '@/types';

const SOURCE_LABELS = {
    deployments: 'GitHub Deployments to production',
    releases: 'published GitHub releases',
};

/**
 * The DORA metrics, as grid cells for a report section.
 */
export function Delivery({
    report,
    labels,
    tooltipLabels,
    dates,
}: {
    report: VelocityReport;
    labels: string[];
    tooltipLabels: string[];
    dates: string[];
}) {
    const timeZone = useTimeZone();
    const { kpis, delivery } = report;

    if (delivery.source === null) {
        return (
            <PanelCell>
                <div className="py-6 text-center">
                    <div className="font-medium">No deploy signal yet</div>
                    <p className="mx-auto mt-1 max-w-lg text-sm text-ink-secondary">
                        Houston measures delivery from GitHub Deployments to a
                        production environment, or from published GitHub
                        releases. This repository has neither in the cached
                        window.
                    </p>
                </div>
            </PanelCell>
        );
    }

    const deploys: TimeSeriesChartProps = {
        kind: 'bar',
        height: 260,
        labels,
        tooltipLabels,
        dates,
        benchmark: bucketThresholds(kpis.deployments, report.period.bucketDays),
        series: [
            {
                key: 'deployments',
                label: delivery.source === 'releases' ? 'Releases' : 'Deploys',
                color: 'var(--series-1)',
                values: report.trend.map((point) => point.deployments),
            },
        ],
        format: (value) => formatNumber(value),
    };

    const tile = (label: string, hint: string, key: MetricKey) => (
        <MetricTile label={label} hint={hint} metric={kpis[key] as Metric} />
    );

    return (
        <>
            {tile(
                'Deploy frequency',
                `${kpis.deployments.value ?? 0} ${delivery.source === 'releases' ? 'releases' : 'deploys'} this period`,
                'deployFrequency',
            )}
            {tile(
                'Lead time to production',
                'First commit → shipped, median',
                'leadTimeToProduction',
            )}
            {tile(
                'Change failure rate',
                'Deploys followed by a revert or hotfix',
                'changeFailureRate',
            )}
            {tile(
                'Time to restore',
                'Failed change → next deploy, median',
                'timeToRestore',
            )}

            <ChartPanel
                span="7/12"
                title={delivery.source === 'releases' ? 'Releases' : 'Deploys'}
                metric={report.trends.deployments}
                description={`Measured from ${SOURCE_LABELS[delivery.source]}`}
                chart={deploys}
                actions={
                    delivery.pending.count > 0 ? (
                        <Badge tone="warn">
                            {delivery.pending.count} merged PR
                            {delivery.pending.count === 1 ? '' : 's'} not
                            shipped · oldest{' '}
                            {formatDuration(delivery.pending.oldestSeconds)}
                        </Badge>
                    ) : (
                        <Badge tone="good">Everything merged is shipped</Badge>
                    )
                }
            >
                {kpis.deploymentFailureRate.value !== null && (
                    <p className="mt-3 flex items-center gap-2 text-xs text-ink-muted">
                        {formatNumber(
                            (kpis.deploymentFailureRate.value ?? 0) * 100,
                        )}
                        % of deployment attempts failed this period.
                        <BenchmarkBadge
                            metric={kpis.deploymentFailureRate}
                            label="Deployment failure rate"
                            hint="Deployment attempts that failed"
                        />
                    </p>
                )}
            </ChartPanel>
            <PanelCell
                span="5/12"
                title="Recent"
                bodyClassName="px-0 pb-2 pt-3"
            >
                {delivery.recent.length === 0 ? (
                    <p className="px-5 py-6 text-center text-sm text-ink-muted">
                        Nothing shipped in the cached window.
                    </p>
                ) : (
                    <ul className="divide-y divide-line">
                        {delivery.recent.map((deploy) => (
                            <li
                                key={deploy.name + deploy.deployedAt}
                                className="flex items-center gap-3 px-5 py-2.5 text-sm"
                            >
                                <div className="min-w-0 flex-1">
                                    <div className="flex items-center gap-2">
                                        {deploy.url ? (
                                            <a
                                                href={deploy.url}
                                                target="_blank"
                                                rel="noreferrer"
                                                className="truncate font-medium hover:text-accent"
                                            >
                                                {deploy.name}
                                            </a>
                                        ) : (
                                            <span className="truncate font-medium">
                                                {deploy.name}
                                            </span>
                                        )}
                                        {deploy.failedChange && (
                                            <Badge tone="bad">
                                                <AlertIcon
                                                    width={11}
                                                    height={11}
                                                />
                                                Needed a fix
                                            </Badge>
                                        )}
                                    </div>
                                    <div className="text-[11px] text-ink-muted">
                                        {deploy.ref && `${deploy.ref} · `}
                                        {formatDate(
                                            deploy.deployedAt,
                                            false,
                                            timeZone,
                                        )}{' '}
                                        · {timeAgo(deploy.deployedAt)}
                                    </div>
                                </div>
                                <span className="text-xs whitespace-nowrap text-ink-secondary">
                                    {deploy.shipped} PR
                                    {deploy.shipped === 1 ? '' : 's'}
                                </span>
                            </li>
                        ))}
                    </ul>
                )}
            </PanelCell>
        </>
    );
}
