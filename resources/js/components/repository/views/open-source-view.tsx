import { Dependencies } from '@/components/repository/dependencies';
import { Headline } from '@/components/event-comparison';
import { trendCharts } from '@/components/repository/views/trend-charts';
import { WorkInProgress } from '@/components/repository/work-in-progress';
import { Cell, Grid } from '@/components/report/grid';
import {
    ChartPanel,
    MetricTile,
    PanelCell,
    Section,
} from '@/components/report/widgets';
import { Badge } from '@/components/ui/badge';
import { formatDuration, formatNumber } from '@/lib/format';
import type { RepositoryViewProps } from '@/components/repository/views';

/**
 * A repository as an open source project sees it: how many contributions come in and land, how fast
 * contributors hear back, what waits on maintainers, and how often it ships a release.
 */
export function OpenSourceView({
    report,
    periodLabel,
    dependencies,
}: RepositoryViewProps) {
    const { kpis, trends, period, delivery } = report;
    const { labels, tooltipLabels, dates, charts } = trendCharts(report);
    const shipsWith = delivery.source === 'releases' ? 'Releases' : 'Deploys';

    return (
        <>
            <Grid className="mt-8">
                <Headline>
                    <MetricTile
                        span="1/6"
                        size="lg"
                        label="Contributions opened"
                        hint={periodLabel}
                        metric={kpis.opened}
                    />
                    <MetricTile
                        span="1/6"
                        size="lg"
                        label="Contributions merged"
                        hint={periodLabel}
                        metric={kpis.throughput}
                    />
                    <MetricTile
                        span="1/6"
                        size="lg"
                        label="First response"
                        hint="Ready → first response, median"
                        metric={kpis.pickupTime}
                    />
                    <MetricTile
                        span="1/6"
                        size="lg"
                        label="Time to merge"
                        hint="Opened → merged, median"
                        metric={kpis.cycleTime}
                    />
                    <MetricTile
                        span="1/6"
                        size="lg"
                        label="Open PRs"
                        hint="Right now"
                        metric={kpis.openPullRequests}
                        footer={
                            (kpis.stalePullRequests.value ?? 0) > 0 ? (
                                <Badge tone="warn">
                                    {kpis.stalePullRequests.value} stale
                                </Badge>
                            ) : (
                                <Badge tone="good">None stale</Badge>
                            )
                        }
                    />
                    <MetricTile
                        span="1/6"
                        size="lg"
                        label="Contributors"
                        hint="Opened, merged or reviewed a PR"
                        metric={kpis.activeDevelopers}
                    />
                </Headline>
            </Grid>

            <p className="mt-3 text-xs text-ink-muted">
                Response and merge times count working time:{' '}
                {report.workingTime}.
            </p>

            <Section
                title="Contributions"
                description={`Per ${period.granularity}`}
            >
                <ChartPanel
                    title={trends.throughput.title}
                    metric={trends.throughput}
                    description={trends.throughput.summary}
                    chart={charts.throughput}
                />
                <ChartPanel
                    title={trends.openPullRequests.title}
                    metric={trends.openPullRequests}
                    description={trends.openPullRequests.summary}
                    chart={charts.openPullRequests}
                />
            </Section>

            <Section
                title="Responsiveness"
                description="How long contributors wait on maintainers"
            >
                <ChartPanel
                    title={trends.pickupTime.title}
                    metric={trends.pickupTime}
                    description={trends.pickupTime.summary}
                    chart={charts.pickupTime}
                />
                <ChartPanel
                    title={trends.timeToMerge.title}
                    metric={trends.timeToMerge}
                    description={trends.timeToMerge.summary}
                    chart={charts.timeToMerge}
                />
                <Cell>
                    <WorkInProgress pullRequests={report.workInProgress} />
                </Cell>
            </Section>

            <Section title={shipsWith}>
                {delivery.source === null ? (
                    <PanelCell>
                        <p className="py-6 text-center text-sm text-ink-secondary">
                            No published GitHub releases or production
                            deployments in the cached window.
                        </p>
                    </PanelCell>
                ) : (
                    <>
                        <MetricTile
                            span="1/3"
                            label={`${shipsWith.slice(0, -1)} frequency`}
                            hint={`${kpis.deployments.value ?? 0} ${shipsWith.toLowerCase()} this period`}
                            metric={kpis.deployFrequency}
                        />
                        <MetricTile
                            span="1/3"
                            label="Time to ship"
                            hint="First commit → released, median"
                            metric={kpis.leadTimeToProduction}
                        />
                        <PanelCell
                            span="1/3"
                            title="Merged, not yet shipped"
                            description={
                                delivery.pending.count > 0
                                    ? `Oldest waiting ${formatDuration(delivery.pending.oldestSeconds)}`
                                    : `Everything merged is in a ${shipsWith.slice(0, -1).toLowerCase()}`
                            }
                        >
                            <div className="text-2xl font-semibold tabular-nums">
                                {formatNumber(delivery.pending.count)}
                            </div>
                        </PanelCell>
                        <ChartPanel
                            span="full"
                            title={shipsWith}
                            metric={trends.deployments}
                            description={`Per ${period.granularity}`}
                            chart={{
                                kind: 'bar',
                                height: 180,
                                labels,
                                tooltipLabels,
                                dates,
                                series: [
                                    {
                                        key: 'deployments',
                                        label: shipsWith,
                                        color: 'var(--series-1)',
                                        values: report.trend.map(
                                            (point) => point.deployments,
                                        ),
                                    },
                                ],
                                format: (value) => formatNumber(value),
                            }}
                        />
                    </>
                )}
            </Section>

            {dependencies !== null && dependencies.total > 0 && (
                <Section title="Dependencies">
                    <Cell>
                        <Dependencies dependencies={dependencies} />
                    </Cell>
                </Section>
            )}
        </>
    );
}
