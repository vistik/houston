import { BenchmarkBadge } from '@/components/metric-card';
import { Dependencies } from '@/components/repository/dependencies';
import { WorkflowsTable } from '@/components/repository/workflows-table';
import { Cell } from '@/components/report/grid';
import { MetricList, MetricTile, Section } from '@/components/report/widgets';
import { formatDuration } from '@/lib/format';
import type { RepositoryViewProps } from '@/components/repository/views';

/**
 * How the GitHub Actions pipeline is doing: failures, red time, duration and every workflow. More than one
 * view of a repository shows it.
 */
export function CiHealth({
    report,
    defaultBranch,
    repository,
}: Pick<RepositoryViewProps, 'report' | 'defaultBranch' | 'repository'>) {
    const { kpis, ciFailureRateScope } = report;

    return (
        <Section title="CI health" description="GitHub Actions">
            <MetricTile
                label={`Failure rate on ${defaultBranch ?? 'default branch'}`}
                hint="Pushes to the default branch"
                metric={kpis.ciDefaultBranchFailureRate}
            />
            <MetricTile
                label={`Time ${defaultBranch ?? 'default branch'} was red`}
                hint="Share of working time"
                metric={kpis.defaultBranchRedShare}
            />
            <MetricTile
                label="CI duration"
                hint="Median per commit"
                metric={kpis.ciDuration}
                footer={
                    kpis.ciDurationP90.value !== null && (
                        <div className="flex items-center gap-1.5 text-[11px] text-ink-muted">
                            p90
                            <span className="tabular text-sm font-semibold text-ink">
                                {formatDuration(kpis.ciDurationP90.value)}
                            </span>
                            <BenchmarkBadge
                                metric={kpis.ciDurationP90}
                                label="CI duration (p90)"
                                hint="The slow tail"
                            />
                        </div>
                    )
                }
            />
            <MetricTile
                label="Time to recovery"
                hint={`${defaultBranch ?? 'Default branch'} red → green, median`}
                metric={kpis.ciRecoveryTime}
            />
            {/* The workflows table has too many columns to share a row, so the pipeline sits above it. */}
            <MetricList
                title="Pipeline"
                items={[
                    {
                        key: 'queueTime',
                        label: 'Queue time',
                        hint: 'Waiting for a runner, median',
                        metric: kpis.ciQueueTime,
                    },
                    {
                        key: 'flaky',
                        label: 'Flaky commits',
                        hint: 'Failed and passed on the same commit',
                        metric: kpis.ciFlakyRate,
                    },
                    {
                        key: 'pullRequestFailureRate',
                        label: 'PR build failure rate',
                        hint: 'pull_request runs',
                        metric: kpis.ciPullRequestFailureRate,
                    },
                    {
                        key: 'rerunRate',
                        label: 'Re-run rate',
                        hint: 'Runs retried at least once',
                        metric: kpis.ciRerunRate,
                    },
                    {
                        key: 'runs',
                        label: 'Workflow runs',
                        metric: kpis.ciRuns,
                    },
                    {
                        key: 'minutes',
                        label: 'CI minutes',
                        hint: 'Wall clock, all runs',
                        metric: kpis.ciMinutes,
                    },
                    // On the default branch the headline rate is the tile above, so only list it when it covers every branch.
                    ...(ciFailureRateScope === 'all_branches'
                        ? [
                              {
                                  key: 'failureRate',
                                  label: 'Overall failure rate',
                                  metric: kpis.ciFailureRate,
                              },
                          ]
                        : []),
                ]}
            />
            <Cell>
                <WorkflowsTable
                    workflows={report.workflows}
                    repository={repository}
                />
            </Cell>
        </Section>
    );
}

/** How far behind the direct dependencies are, for repositories that have any. */
export function DependenciesSection({
    dependencies,
}: Pick<RepositoryViewProps, 'dependencies'>) {
    if (dependencies === null || dependencies.total === 0) {
        return null;
    }

    return (
        <Section title="Dependencies">
            <Cell>
                <Dependencies dependencies={dependencies} />
            </Cell>
        </Section>
    );
}
