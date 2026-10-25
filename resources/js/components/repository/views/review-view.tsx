import { StageBar } from '@/components/charts/stage-bar';
import { FlowAndReviewQuality } from '@/components/repository/flow-and-review-quality';
import { ActiveDevelopers } from '@/components/repository/people';
import { SlowestMerged } from '@/components/repository/slowest-merged';
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
import type { RepositoryViewProps } from '@/components/repository/views';
import { useComparedWith } from '@/lib/timeline-events';

/**
 * A repository as its reviewers see it: how long pull requests wait for a first look and an approval,
 * how much back and forth they take, and who does the reviewing.
 */
export function ReviewView({
    report,
    periodLabel,
    defaultBranch,
}: RepositoryViewProps) {
    const { kpis, trends, period } = report;
    const { labels, tooltipLabels, dates, charts } = trendCharts(report);
    const comparedWith = useComparedWith();

    return (
        <>
            <Grid className="mt-8">
                <Headline>
                    <MetricTile
                        span="1/6"
                        size="lg"
                        label="Pickup time"
                        hint="Ready → first response, median"
                        metric={kpis.pickupTime}
                    />
                    <MetricTile
                        span="1/6"
                        size="lg"
                        label="Time to approval"
                        hint="Ready → approved, median"
                        metric={kpis.timeToApproval}
                    />
                    <MetricTile
                        span="1/6"
                        size="lg"
                        label="Review time"
                        hint="First response → merged, median"
                        metric={kpis.reviewTime}
                    />
                    <MetricTile
                        span="1/6"
                        size="lg"
                        label="Review rounds"
                        hint="Per merged PR, average"
                        metric={kpis.reviewRounds}
                    />
                    <MetricTile
                        span="1/6"
                        size="lg"
                        label="Merged unreviewed"
                        hint="Share of merged PRs"
                        metric={kpis.unreviewedMergeRate}
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
                </Headline>

                <PanelCell
                    span="full"
                    title="Where the time goes"
                    description={`Average per PR merged in the ${periodLabel}, from first commit to merge`}
                >
                    <StageBar stages={report.cycleBreakdown} />
                </PanelCell>
            </Grid>

            <p className="mt-3 text-xs text-ink-muted">
                Review times count working time: {report.workingTime}.
            </p>

            <Section
                title="Waiting on review"
                description={`Per ${period.granularity}`}
            >
                <ChartPanel
                    title={trends.pickupTime.title}
                    metric={trends.pickupTime}
                    description={trends.pickupTime.summary}
                    chart={charts.pickupTime}
                />
                <ChartPanel
                    title={trends.openPullRequests.title}
                    metric={trends.openPullRequests}
                    description={trends.openPullRequests.summary}
                    chart={charts.openPullRequests}
                />
                <Cell>
                    <WorkInProgress pullRequests={report.workInProgress} />
                </Cell>
            </Section>

            <Section
                title="Review quality"
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

            <Section title="Reviewers" description={periodLabel}>
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
