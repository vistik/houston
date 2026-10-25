import { StageBar } from '@/components/charts/stage-bar';
import { BenchmarkBadge } from '@/components/metric-card';
import {
    BotPullRequests,
    Remediations,
} from '@/components/repository/attention';
import { Delivery as DeliverySection } from '@/components/repository/delivery';
import { FlowAndReviewQuality } from '@/components/repository/flow-and-review-quality';
import { ActiveDevelopers } from '@/components/repository/people';
import { SlowestMerged } from '@/components/repository/slowest-merged';
import {
    CiHealth,
    DependenciesSection,
} from '@/components/repository/views/sections';
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
import { formatNumber } from '@/lib/format';
import type { RepositoryViewProps } from '@/components/repository/views';
import { useComparedWith } from '@/lib/timeline-events';

/**
 * Everything Houston measures about a repository.
 */
export function FullView({
    report,
    periodLabel,
    defaultBranch,
    repository,
    dependencies,
}: RepositoryViewProps) {
    const { kpis, trends, period, ciFailureRateScope } = report;
    const { labels, tooltipLabels, dates, charts } = trendCharts(report);
    const comparedWith = useComparedWith();

    return (
        <>
            <Grid className="mt-8">
                <Headline>
                    <MetricTile
                        span="1/6"
                        size="lg"
                        label="Time to merge"
                        hint="TTL · opened → merged, median"
                        metric={kpis.cycleTime}
                    />
                    <MetricTile
                        span="1/6"
                        size="lg"
                        label="Pickup time"
                        hint="Ready → first response"
                        metric={kpis.pickupTime}
                    />
                    <MetricTile
                        span="1/6"
                        size="lg"
                        label="Review time"
                        hint="First response → merged"
                        metric={kpis.reviewTime}
                    />
                    <MetricTile
                        span="1/6"
                        size="lg"
                        label="Merged PRs"
                        hint={periodLabel}
                        metric={kpis.throughput}
                    />
                    <MetricTile
                        span="1/6"
                        size="lg"
                        label="CI failure rate"
                        hint={
                            ciFailureRateScope === 'default_branch'
                                ? `Failed / finished runs on ${defaultBranch ?? 'the default branch'}`
                                : 'Failed / finished runs, all branches'
                        }
                        metric={kpis.ciFailureRate}
                    />
                    <MetricTile
                        span="1/6"
                        size="lg"
                        label="Open PRs"
                        hint="Right now"
                        metric={kpis.openPullRequests}
                        footer={
                            <div className="flex flex-wrap items-center gap-1.5">
                                {(kpis.stalePullRequests.value ?? 0) > 0 ? (
                                    <Badge tone="warn">
                                        {kpis.stalePullRequests.value} stale
                                    </Badge>
                                ) : (
                                    <Badge tone="good">None stale</Badge>
                                )}
                                <BenchmarkBadge
                                    metric={kpis.stalePullRequests}
                                    label="Stale PRs"
                                    hint="Open without recent activity"
                                />
                            </div>
                        }
                    />
                </Headline>

                <PanelCell
                    span="7/12"
                    title="Where the time goes"
                    description={`Average per PR merged in the ${periodLabel}, from first commit to merge`}
                >
                    <StageBar stages={report.cycleBreakdown} />
                </PanelCell>
                <ChartPanel
                    span="5/12"
                    title="Time to merge distribution"
                    description="How many PRs landed within each window"
                    chart={{
                        kind: 'bar',
                        height: 150,
                        labels: report.cycleDistribution.map(
                            (bucket) => bucket.label,
                        ),
                        series: [
                            {
                                key: 'count',
                                label: 'Pull requests',
                                color: 'var(--series-1)',
                                values: report.cycleDistribution.map(
                                    (bucket) => bucket.count,
                                ),
                            },
                        ],
                        format: (value) => formatNumber(value),
                    }}
                />
            </Grid>

            <p className="mt-3 text-xs text-ink-muted">
                Durations that measure people count working time:{' '}
                {report.workingTime}. CI durations are wall-clock time.
            </p>

            <Section
                title="Delivery"
                description={`DORA metrics, compared with ${comparedWith}`}
            >
                <DeliverySection
                    report={report}
                    labels={labels}
                    tooltipLabels={tooltipLabels}
                    dates={dates}
                />
            </Section>

            <Section title="Trends" description={`Per ${period.granularity}`}>
                <ChartPanel
                    title={trends.throughput.title}
                    metric={trends.throughput}
                    description={trends.throughput.summary}
                    chart={charts.throughput}
                />
                <ChartPanel
                    title={trends.timeToMerge.title}
                    metric={trends.timeToMerge}
                    description={trends.timeToMerge.summary}
                    chart={charts.timeToMerge}
                />
                <ChartPanel
                    title={trends.pickupTime.title}
                    metric={trends.pickupTime}
                    description={trends.pickupTime.summary}
                    chart={charts.pickupTime}
                />
                <ChartPanel
                    title={trends.ciFailureRate.title}
                    metric={trends.ciFailureRate}
                    description={trends.ciFailureRate.summary}
                    chart={charts.ciFailureRate}
                />
                <ChartPanel
                    span="full"
                    title={trends.openPullRequests.title}
                    metric={trends.openPullRequests}
                    description={trends.openPullRequests.summary}
                    chart={charts.openPullRequests}
                />
            </Section>

            <Section
                title="Flow & review quality"
                description={`Compared with ${comparedWith} · pick a metric to see it over time`}
            >
                <FlowAndReviewQuality
                    report={report}
                    defaultBranch={defaultBranch}
                    labels={labels}
                    tooltipLabels={tooltipLabels}
                    dates={dates}
                />
            </Section>

            <CiHealth
                report={report}
                defaultBranch={defaultBranch}
                repository={repository}
            />

            <Section title="Work in progress">
                <Cell>
                    <WorkInProgress pullRequests={report.workInProgress} />
                </Cell>
                <Cell span="1/2">
                    <Remediations remediations={report.remediations} />
                </Cell>
                <Cell span="1/2">
                    <BotPullRequests backlog={report.botBacklog} />
                </Cell>
            </Section>

            <DependenciesSection dependencies={dependencies} />

            <Section title="People" description={periodLabel}>
                <ActiveDevelopers
                    report={report}
                    labels={labels}
                    tooltipLabels={tooltipLabels}
                    dates={dates}
                />
            </Section>

            <Section
                title="Slowest merges"
                description="The PRs that dragged the tail out"
            >
                <Cell>
                    <SlowestMerged pullRequests={report.slowestMerged} />
                </Cell>
            </Section>
        </>
    );
}
