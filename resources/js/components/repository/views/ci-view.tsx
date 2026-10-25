import { CiHealth } from '@/components/repository/views/sections';
import { Headline } from '@/components/event-comparison';
import { trendCharts } from '@/components/repository/views/trend-charts';
import { Grid } from '@/components/report/grid';
import { ChartPanel, MetricTile, Section } from '@/components/report/widgets';
import type { RepositoryViewProps } from '@/components/repository/views';

/**
 * A repository as the people keeping its pipeline green see it: how often CI fails, how long the default
 * branch stays red, how slow and flaky the runs are, and which workflows are to blame.
 */
export function CiView({
    report,
    defaultBranch,
    repository,
}: RepositoryViewProps) {
    const { kpis, trends, period, ciFailureRateScope } = report;
    const { charts } = trendCharts(report);
    const branch = defaultBranch ?? 'the default branch';

    return (
        <>
            <Grid className="mt-8">
                <Headline>
                    <MetricTile
                        span="1/6"
                        size="lg"
                        label="CI failure rate"
                        hint={
                            ciFailureRateScope === 'default_branch'
                                ? `Failed / finished runs on ${branch}`
                                : 'Failed / finished runs, all branches'
                        }
                        icon="ciFailureRate"
                        metric={kpis.ciFailureRate}
                    />
                    <MetricTile
                        span="1/6"
                        size="lg"
                        label={`Time ${branch} was red`}
                        hint="Share of working time"
                        icon="defaultBranchRedShare"
                        metric={kpis.defaultBranchRedShare}
                    />
                    <MetricTile
                        span="1/6"
                        size="lg"
                        label="Time to recovery"
                        hint="Red → green, median"
                        icon="ciRecoveryTime"
                        metric={kpis.ciRecoveryTime}
                    />
                    <MetricTile
                        span="1/6"
                        size="lg"
                        label="CI duration"
                        hint="Median per commit"
                        icon="ciDuration"
                        metric={kpis.ciDuration}
                    />
                    <MetricTile
                        span="1/6"
                        size="lg"
                        label="Flaky commits"
                        hint="Failed and passed on the same commit"
                        icon="ciFlakyRate"
                        metric={kpis.ciFlakyRate}
                    />
                    <MetricTile
                        span="1/6"
                        size="lg"
                        label="Queue time"
                        hint="Waiting for a runner, median"
                        icon="ciQueueTime"
                        metric={kpis.ciQueueTime}
                    />
                </Headline>
            </Grid>

            <Section
                title="Over time"
                description={`Per ${period.granularity}`}
            >
                <ChartPanel
                    span="full"
                    title={trends.ciFailureRate.title}
                    metric={trends.ciFailureRate}
                    description={trends.ciFailureRate.summary}
                    chart={charts.ciFailureRate}
                />
            </Section>

            <CiHealth
                report={report}
                defaultBranch={defaultBranch}
                repository={repository}
            />
        </>
    );
}
