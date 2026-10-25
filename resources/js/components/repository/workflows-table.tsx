import { Link, router, usePage } from '@inertiajs/react';
import { use, useState } from 'react';
import type { ReactNode } from 'react';
import { ArrowDownIcon, ArrowUpIcon, ExpandIcon } from '@/components/icons';
import { Tooltip } from '@/components/ui/hover-card';
import { Panel } from '@/components/ui/panel';
import { formatDuration, formatNumber, formatPercent } from '@/lib/format';
import { MetricHistoryContext } from '@/lib/metric-history';
import { cn } from '@/lib/utils';
import { excludedWorkflows } from '@/routes/repositories';
import type { Metric, MetricKey, RepositoryInfo, WorkflowStats } from '@/types';

type SortKey =
    | 'name'
    | 'runs'
    | 'failureRate'
    | 'defaultBranchFailureRate'
    | 'flakyRate'
    | 'queueTime'
    | 'duration'
    | 'durationP90'
    | 'minutes'
    | 'included';

type Sort = { key: SortKey; direction: 'asc' | 'desc' };

/** The repository's CI metric each column breaks down per workflow, to open it on its own page. */
const COLUMN_METRICS: Partial<Record<SortKey, MetricKey>> = {
    runs: 'ciRuns',
    failureRate: 'ciFailureRate',
    defaultBranchFailureRate: 'ciDefaultBranchFailureRate',
    flakyRate: 'ciFlakyRate',
    queueTime: 'ciQueueTime',
    duration: 'ciDuration',
    durationP90: 'ciDurationP90',
    minutes: 'ciMinutes',
};

const SORT_VALUES: Record<
    SortKey,
    (workflow: WorkflowStats) => number | string | null
> = {
    name: (workflow) => workflow.name.toLowerCase(),
    runs: (workflow) => workflow.runs,
    failureRate: (workflow) => workflow.failureRate,
    defaultBranchFailureRate: (workflow) => workflow.defaultBranchFailureRate,
    flakyRate: (workflow) => workflow.flakyRate,
    queueTime: (workflow) => workflow.queueTime,
    duration: (workflow) => workflow.duration,
    durationP90: (workflow) => workflow.durationP90,
    minutes: (workflow) => workflow.minutes,
    included: (workflow) => (workflow.excluded ? 0 : 1),
};

/**
 * Workflows ordered by one column. Workflows without a value always go last, and ties keep the
 * server's busiest-first order.
 */
function sortWorkflows(
    workflows: WorkflowStats[],
    sort: Sort,
): WorkflowStats[] {
    const value = SORT_VALUES[sort.key];
    const sign = sort.direction === 'asc' ? 1 : -1;

    return workflows
        .map((workflow, index) => ({ workflow, index }))
        .sort((a, b) => {
            const left = value(a.workflow);
            const right = value(b.workflow);

            if (left === null || right === null) {
                return left === right
                    ? a.index - b.index
                    : left === null
                      ? 1
                      : -1;
            }

            const order = left < right ? -1 : left > right ? 1 : 0;

            return order === 0 ? a.index - b.index : order * sign;
        })
        .map(({ workflow }) => workflow);
}

export function WorkflowsTable({
    workflows,
    repository,
    kpis,
}: {
    workflows: WorkflowStats[];
    repository: RepositoryInfo;
    /** The repository's metrics, so each column can open the CI metric it breaks down. */
    kpis?: Partial<Record<MetricKey, Metric>>;
}) {
    const { demo } = usePage().props;
    const [sort, setSort] = useState<Sort>({ key: 'runs', direction: 'desc' });
    const sorted = sortWorkflows(workflows, sort);

    // Names read best A–Z; every other column is most interesting highest first.
    const sortBy = (key: SortKey) =>
        setSort((current) =>
            current.key === key
                ? {
                      key,
                      direction: current.direction === 'asc' ? 'desc' : 'asc',
                  }
                : { key, direction: key === 'name' ? 'asc' : 'desc' },
        );
    const header = (key: SortKey) => ({
        sortKey: key,
        sort,
        onSort: sortBy,
        metric: COLUMN_METRICS[key] && kpis?.[COLUMN_METRICS[key]],
    });

    // Toggle against the stored list, which also holds workflows that didn't run in this period.
    const toggle = (workflow: WorkflowStats) => {
        const excluded = repository.excludedWorkflowIds;

        router.put(
            excludedWorkflows(repository.id).url,
            {
                workflow_ids: workflow.excluded
                    ? excluded.filter((id) => id !== workflow.workflowId)
                    : [...excluded, workflow.workflowId],
            },
            { preserveScroll: true },
        );
    };

    return (
        <Panel
            title="Workflows"
            description="GitHub Actions workflows that ran in this period. Exclude the ones that aren't CI, like label checks, to keep them out of the CI metrics."
            bodyClassName="px-0 pb-2 pt-3"
        >
            {workflows.length === 0 ? (
                <p className="px-5 py-8 text-center text-sm text-ink-muted">
                    No workflow runs in this period.
                </p>
            ) : (
                <div className="overflow-x-auto">
                    <table className="w-full text-sm whitespace-nowrap">
                        <thead className="text-left text-[11px] text-ink-muted">
                            <tr className="border-b border-line">
                                <SortHeader
                                    {...header('name')}
                                    className="px-5"
                                    align="left"
                                >
                                    Workflow
                                </SortHeader>
                                <SortHeader {...header('runs')}>
                                    Runs
                                </SortHeader>
                                <SortHeader
                                    {...header('failureRate')}
                                    align="left"
                                >
                                    Failure rate
                                </SortHeader>
                                <SortHeader
                                    {...header('defaultBranchFailureRate')}
                                    className="hidden md:table-cell"
                                >
                                    Default branch
                                </SortHeader>
                                <SortHeader
                                    {...header('flakyRate')}
                                    className="hidden md:table-cell"
                                    hint="Commits where this workflow both failed and passed"
                                >
                                    Flaky
                                </SortHeader>
                                <SortHeader
                                    {...header('queueTime')}
                                    className="hidden lg:table-cell"
                                    hint="Waiting for a runner, median"
                                >
                                    Queue
                                </SortHeader>
                                <SortHeader {...header('duration')}>
                                    Median
                                </SortHeader>
                                <SortHeader
                                    {...header('durationP90')}
                                    className="hidden sm:table-cell"
                                >
                                    p90
                                </SortHeader>
                                <SortHeader
                                    {...header('minutes')}
                                    className="hidden lg:table-cell"
                                >
                                    Minutes
                                </SortHeader>
                                <SortHeader
                                    {...header('included')}
                                    className="px-5"
                                    hint="Whether this workflow counts towards the CI metrics"
                                >
                                    In metrics
                                </SortHeader>
                            </tr>
                        </thead>
                        <tbody>
                            {sorted.map((workflow) => (
                                <tr
                                    key={workflow.workflowId}
                                    className={cn(
                                        'border-b border-line last:border-0',
                                        workflow.excluded &&
                                            'text-ink-muted [&>td:not(:last-child)]:opacity-60',
                                    )}
                                >
                                    <td className="px-5 py-2">
                                        <a
                                            href={workflow.lastUrl}
                                            target="_blank"
                                            rel="noreferrer"
                                            className="flex items-center gap-2 hover:text-accent"
                                        >
                                            <span
                                                className={cn(
                                                    'size-2 shrink-0 rounded-full',
                                                    workflow.lastConclusion ===
                                                        'success' && 'bg-good',
                                                    [
                                                        'failure',
                                                        'timed_out',
                                                        'startup_failure',
                                                    ].includes(
                                                        workflow.lastConclusion,
                                                    ) && 'bg-bad',
                                                    ![
                                                        'success',
                                                        'failure',
                                                        'timed_out',
                                                        'startup_failure',
                                                    ].includes(
                                                        workflow.lastConclusion,
                                                    ) && 'bg-line-strong',
                                                )}
                                            />
                                            <Tooltip
                                                className="min-w-0"
                                                content={
                                                    <>
                                                        <div className="font-medium">
                                                            {workflow.name}
                                                        </div>
                                                        <div className="text-ink-muted">
                                                            Last run:{' '}
                                                            {workflow.lastConclusion.replace(
                                                                /_/g,
                                                                ' ',
                                                            )}
                                                        </div>
                                                    </>
                                                }
                                            >
                                                <span className="max-w-56 truncate">
                                                    {workflow.name}
                                                </span>
                                            </Tooltip>
                                            {workflow.excluded && (
                                                <span className="rounded-full bg-line/70 px-1.5 py-0.5 text-[11px] font-medium text-ink-secondary">
                                                    Excluded
                                                </span>
                                            )}
                                        </a>
                                    </td>
                                    <td className="tabular px-3 py-2 text-right text-ink-secondary">
                                        {formatNumber(workflow.runs)}
                                    </td>
                                    <td className="px-3 py-2">
                                        <FailureBar
                                            ratio={workflow.failureRate}
                                        />
                                    </td>
                                    <td className="tabular hidden px-3 py-2 text-right text-ink-secondary md:table-cell">
                                        {formatPercent(
                                            workflow.defaultBranchFailureRate,
                                        )}
                                    </td>
                                    <td
                                        className={cn(
                                            'tabular hidden px-3 py-2 text-right md:table-cell',
                                            (workflow.flakyRate ?? 0) > 0.05
                                                ? 'font-medium text-warn'
                                                : 'text-ink-secondary',
                                        )}
                                    >
                                        {formatPercent(workflow.flakyRate)}
                                    </td>
                                    <td className="tabular hidden px-3 py-2 text-right text-ink-secondary lg:table-cell">
                                        {formatDuration(workflow.queueTime)}
                                    </td>
                                    <td className="tabular px-3 py-2 text-right">
                                        {formatDuration(workflow.duration)}
                                    </td>
                                    <td className="tabular hidden px-3 py-2 text-right text-ink-secondary sm:table-cell">
                                        {formatDuration(workflow.durationP90)}
                                    </td>
                                    <td className="tabular hidden px-3 py-2 text-right text-ink-secondary lg:table-cell">
                                        {formatNumber(workflow.minutes)}
                                    </td>
                                    <td className="px-5 py-2 text-right">
                                        <MetricsSwitch
                                            included={!workflow.excluded}
                                            name={workflow.name}
                                            disabled={demo}
                                            onToggle={() => toggle(workflow)}
                                        />
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}
        </Panel>
    );
}

/**
 * A column header that sorts the table, showing which way it is sorted.
 */
function SortHeader({
    sortKey,
    sort,
    onSort,
    metric,
    align = 'right',
    hint,
    className,
    children,
}: {
    sortKey: SortKey;
    sort: Sort;
    onSort: (key: SortKey) => void;
    /** The repository's metric the column breaks down per workflow, linked when it has a page. */
    metric?: Metric;
    align?: 'left' | 'right';
    hint?: string;
    className?: string;
    children: ReactNode;
}) {
    const href = metric && use(MetricHistoryContext)?.links.get(metric);
    const active = sort.key === sortKey;
    const Arrow = sort.direction === 'asc' ? ArrowUpIcon : ArrowDownIcon;
    const label = hint ? (
        <Tooltip
            content={hint}
            className="cursor-help underline decoration-line-strong decoration-dotted underline-offset-2"
        >
            {children}
        </Tooltip>
    ) : (
        children
    );

    return (
        <th
            aria-sort={
                active
                    ? sort.direction === 'asc'
                        ? 'ascending'
                        : 'descending'
                    : undefined
            }
            className={cn(
                'group px-3 py-2 font-medium',
                align === 'right' ? 'text-right' : 'text-left',
                className,
            )}
        >
            {href && align === 'right' && <ColumnLink href={href} />}
            <button
                type="button"
                onClick={() => onSort(sortKey)}
                className={cn(
                    'inline-flex items-center gap-1 transition hover:text-ink',
                    align === 'right' && 'flex-row-reverse',
                    active && 'text-ink',
                )}
            >
                {label}
                <Arrow
                    width={11}
                    height={11}
                    strokeWidth={2.25}
                    className={active ? 'opacity-100' : 'opacity-0'}
                />
            </button>
            {href && align === 'left' && <ColumnLink href={href} />}
        </th>
    );
}

/**
 * Opens the repository's metric a column breaks down per workflow, on its own page. Shown on hover
 * where there is a pointer to hover with, and always on touch screens.
 */
function ColumnLink({ href }: { href: string }) {
    return (
        <Link
            href={href}
            aria-label="Open this metric for the whole repository"
            title="Open for the whole repository"
            className="mx-0.5 inline-flex rounded p-0.5 align-middle text-ink-muted transition hover:bg-line/60 hover:text-ink focus-visible:opacity-100 focus-visible:ring-2 focus-visible:ring-accent focus-visible:outline-none pointer-fine:opacity-0 pointer-fine:group-hover:opacity-100"
        >
            <ExpandIcon width={11} height={11} />
        </Link>
    );
}

/**
 * Switch for whether a workflow counts towards the CI metrics: green when it does.
 */
function MetricsSwitch({
    included,
    name,
    disabled,
    onToggle,
}: {
    included: boolean;
    name: string;
    disabled: boolean;
    onToggle: () => void;
}) {
    return (
        <button
            type="button"
            role="switch"
            aria-checked={included}
            aria-label={`Count ${name} in the CI metrics`}
            disabled={disabled}
            onClick={onToggle}
            className={cn(
                'relative inline-flex h-4 w-7 shrink-0 items-center rounded-full transition-colors focus-visible:ring-2 focus-visible:ring-accent-soft focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-60',
                included ? 'bg-good' : 'bg-line-strong',
            )}
        >
            <span
                className={cn(
                    'block size-3 rounded-full bg-surface shadow-sm transition-transform',
                    included ? 'translate-x-3.5' : 'translate-x-0.5',
                )}
            />
        </button>
    );
}

function FailureBar({ ratio }: { ratio: number | null }) {
    return (
        <span className="flex items-center gap-2">
            <span className="tabular w-10 font-medium">
                {formatPercent(ratio)}
            </span>
            <span className="hidden h-1.5 w-14 overflow-hidden rounded-full bg-line xl:block">
                <span
                    className={cn(
                        'block h-full rounded-full',
                        (ratio ?? 0) > 0.15
                            ? 'bg-bad'
                            : (ratio ?? 0) > 0.05
                              ? 'bg-warn'
                              : 'bg-good',
                    )}
                    style={{ width: `${Math.min(100, (ratio ?? 0) * 100)}%` }}
                />
            </span>
        </span>
    );
}
