import { Remediations } from '@/components/repository/attention';
import { Delivery as DeliverySection } from '@/components/repository/delivery';
import { DependenciesSection } from '@/components/repository/views/sections';
import { Headline } from '@/components/event-comparison';
import { trendCharts } from '@/components/repository/views/trend-charts';
import { WorkInProgress } from '@/components/repository/work-in-progress';
import { Cell, Grid } from '@/components/report/grid';
import { ChartPanel, MetricTile, Section } from '@/components/report/widgets';
import type { RepositoryViewProps } from '@/components/repository/views';
import { useComparedWith } from '@/lib/timeline-events';

/**
 * A repository as the people shipping it see it: the DORA metrics up front, then what feeds them, from
 * merged pull requests to the fixes that followed a deploy.
 */
export function DeliveryView({
    report,
    periodLabel,
    dependencies,
}: RepositoryViewProps) {
    const { kpis, trends, period, delivery } = report;
    const { labels, tooltipLabels, dates, charts } = trendCharts(report);
    const comparedWith = useComparedWith();
    const releases = delivery.source === 'releases';

    return (
        <>
            <Grid className="mt-8">
                <Headline>
                    <MetricTile
                        span="1/6"
                        size="lg"
                        label={
                            releases ? 'Release frequency' : 'Deploy frequency'
                        }
                        hint={`${kpis.deployments.value ?? 0} ${releases ? 'releases' : 'deploys'} · ${periodLabel}`}
                        icon="deployFrequency"
                        metric={kpis.deployFrequency}
                    />
                    <MetricTile
                        span="1/6"
                        size="lg"
                        label="Lead time to production"
                        hint="First commit → shipped, median"
                        icon="leadTimeToProduction"
                        metric={kpis.leadTimeToProduction}
                    />
                    <MetricTile
                        span="1/6"
                        size="lg"
                        label="Change failure rate"
                        hint="Shipped changes that needed a fix"
                        icon="changeFailureRate"
                        metric={kpis.changeFailureRate}
                    />
                    <MetricTile
                        span="1/6"
                        size="lg"
                        label="Time to restore"
                        hint="Failed change → fix shipped, median"
                        icon="timeToRestore"
                        metric={kpis.timeToRestore}
                    />
                    <MetricTile
                        span="1/6"
                        size="lg"
                        label="Merged PRs"
                        hint={periodLabel}
                        icon="throughput"
                        metric={kpis.throughput}
                    />
                    <MetricTile
                        span="1/6"
                        size="lg"
                        label="Time to merge"
                        hint="Opened → merged, median"
                        icon="cycleTime"
                        metric={kpis.cycleTime}
                    />
                </Headline>
            </Grid>

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

            <Section
                title="What feeds it"
                description={`Per ${period.granularity}`}
            >
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
            </Section>

            <Section title="Fixes and work in progress">
                <Cell span="1/2">
                    <Remediations remediations={report.remediations} />
                </Cell>
                <Cell span="1/2">
                    <WorkInProgress pullRequests={report.workInProgress} />
                </Cell>
            </Section>

            <DependenciesSection dependencies={dependencies} />
        </>
    );
}
