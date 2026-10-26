import { Link } from '@inertiajs/react';
import { use } from 'react';
import type { ReactNode } from 'react';
import { ExternalIcon } from '@/components/icons';
import { DeltaPill } from '@/components/metric-card';
import { HoverCard, Tooltip } from '@/components/ui/hover-card';
import { Panel } from '@/components/ui/panel';
import { formatDurationCompact, formatNumber } from '@/lib/format';
import { MetricHistoryContext } from '@/lib/metric-history';
import { cn } from '@/lib/utils';
import type {
    FlakyJob,
    FlakyJobs as FlakyJobsReport,
    Metric,
    MetricKey,
} from '@/types';

/** The metrics each job's figures break down. */
type FlakyJobMetricKey =
    | 'ciFlakyJobRuns'
    | 'ciFlakyJobWait'
    | 'ciFlakyJobMinutes';

/** How many failing steps a job names before the rest are left to the tooltip. */
const SHOWN_STEPS = 2;

/**
 * The jobs that most often failed and then passed when their run was re-run, the steps they failed in,
 * and what that cost: the time people waited for the re-run, and the CI time of the failed attempts.
 * One level below the flaky commits, so it names what to fix.
 */
export function FlakyJobs({
    flakyJobs,
    kpis,
}: {
    flakyJobs: FlakyJobsReport;
    /** The repository's metrics, so each figure can open its metric for the one job. */
    kpis?: Partial<Record<MetricKey, Metric>>;
}) {
    const { jobs, count, seconds, waitSeconds, pendingRuns } = flakyJobs;
    const links = use(MetricHistoryContext)?.links;
    const link = (key: FlakyJobMetricKey, job: FlakyJob) => {
        const metric = kpis?.[key];
        const href = metric && links?.get(metric);

        return href ? jobMetricUrl(href, job) : undefined;
    };
    const pending =
        pendingRuns > 0 &&
        `${formatNumber(pendingRuns)} re-run${pendingRuns === 1 ? '' : 's'} still waiting for ${pendingRuns === 1 ? 'its' : 'their'} jobs, which the next sync fetches.`;

    return (
        <Panel
            title="Flakiest jobs"
            description={
                jobs.length === 0
                    ? 'Jobs that failed and then passed on a re-run'
                    : `${formatNumber(count)} job${count === 1 ? '' : 's'} failed and then passed on a re-run, keeping people waiting ${formatDurationCompact(waitSeconds)} and costing ${formatDurationCompact(seconds)} of CI`
            }
            bodyClassName="px-0 pb-2 pt-3"
        >
            {jobs.length === 0 ? (
                <p className="px-5 py-6 text-center text-sm text-ink-muted">
                    {pending ||
                        'No job failed and then passed on a re-run in this period.'}
                </p>
            ) : (
                <>
                    <ol className="divide-y divide-line border-t border-line">
                        {jobs.map((job) => (
                            <li
                                key={`${job.workflowId}:${job.name}`}
                                className="relative flex items-center gap-4 px-5 py-2.5 transition hover:bg-line/30"
                            >
                                <div className="min-w-0 flex-1">
                                    <div className="flex min-w-0 items-center gap-2">
                                        <JobName
                                            job={job}
                                            href={link('ciFlakyJobRuns', job)}
                                        />
                                        <span className="relative z-10 flex shrink-0">
                                            <RunsChange job={job} />
                                        </span>
                                        {job.url && (
                                            <a
                                                href={job.url}
                                                target="_blank"
                                                rel="noreferrer"
                                                aria-label={`Logs of the latest failed attempt of ${job.name}`}
                                                title="Logs of the latest failed attempt"
                                                className="relative z-10 shrink-0 rounded p-0.5 text-ink-muted transition hover:bg-line/60 hover:text-ink"
                                            >
                                                <ExternalIcon
                                                    width={12}
                                                    height={12}
                                                />
                                            </a>
                                        )}
                                    </div>
                                    <div className="flex min-w-0 items-center gap-1 text-[11px] text-ink-muted">
                                        <span className="shrink-0">
                                            {job.workflow}
                                        </span>
                                        <FailedSteps job={job} />
                                    </div>
                                </div>
                                <Figure label="Flaky runs">
                                    {formatNumber(job.runs)}
                                </Figure>
                                <Figure
                                    label="Waiting"
                                    hint="Working time from a failed attempt finishing to the re-run passing"
                                    href={link('ciFlakyJobWait', job)}
                                    strong
                                >
                                    {formatDurationCompact(job.waitSeconds)}
                                </Figure>
                                <Figure
                                    label="CI lost"
                                    hint={`${formatNumber(job.failures)} failed attempt${job.failures === 1 ? '' : 's'}, run again until it passed`}
                                    href={link('ciFlakyJobMinutes', job)}
                                    className="hidden sm:block"
                                >
                                    {formatDurationCompact(job.seconds)}
                                </Figure>
                            </li>
                        ))}
                    </ol>
                    {pending && (
                        <p className="border-t border-line px-5 pt-2 text-[11px] text-ink-muted">
                            {pending}
                        </p>
                    )}
                </>
            )}
        </Panel>
    );
}

/**
 * A flaky-job metric's page narrowed to one job, keeping the period and options of its link.
 */
function jobMetricUrl(href: string, job: FlakyJob): string {
    const url = new URL(href, window.location.origin);
    url.searchParams.set('workflow', String(job.workflowId));
    url.searchParams.set('job', job.name);

    return url.pathname + url.search;
}

/**
 * The steps a job's failed attempts failed in, most often first, marking GitHub's setup steps since
 * those usually mean the runner or the network rather than the tests.
 */
function FailedSteps({ job }: { job: FlakyJob }) {
    if (job.steps.length === 0) {
        return null;
    }

    const hidden = job.steps.length - SHOWN_STEPS;

    return (
        <HoverCard
            className="relative z-10 min-w-0"
            panelClassName="max-w-80 rounded-lg px-2.5 py-1.5 text-ink"
            card={
                <ul className="space-y-0.5">
                    {job.steps.map((step) => (
                        <li
                            key={step.name}
                            className="flex justify-between gap-4"
                        >
                            <span>
                                {step.name}
                                {step.setup && (
                                    <span className="text-ink-muted">
                                        {' '}
                                        · setup, likely the runner or network
                                    </span>
                                )}
                            </span>
                            <span className="tabular text-ink-muted">
                                {formatNumber(step.failures)}×
                            </span>
                        </li>
                    ))}
                </ul>
            }
        >
            {/* One span, so the trigger's flex layout keeps the spaces between the pieces. */}
            <span className="truncate">
                · failed in{' '}
                {job.steps.slice(0, SHOWN_STEPS).map((step, index) => (
                    <span key={step.name}>
                        {index > 0 && ', '}
                        <span className="text-ink-secondary">{step.name}</span>
                        {step.setup && (
                            <span className="ml-1 rounded-full bg-line/70 px-1.5 py-px font-medium text-ink-secondary">
                                setup
                            </span>
                        )}
                        {job.steps.length > 1 && ` ×${step.failures}`}
                    </span>
                ))}
                {hidden > 0 && ` +${hidden} more`}
            </span>
        </HoverCard>
    );
}

/**
 * The job's name, linking the whole row to how often it was flaky over time. The link stretches over
 * the row, and what else in it can be clicked or hovered sits above it.
 */
function JobName({ job, href }: { job: FlakyJob; href?: string }) {
    const classes = 'truncate text-sm font-medium';

    return href ? (
        <Link
            href={href}
            title="Flaky runs over time for this job"
            className={cn(
                classes,
                'after:absolute after:inset-0 focus-visible:underline focus-visible:outline-none',
            )}
        >
            {job.name}
        </Link>
    ) : (
        <span className={classes}>{job.name}</span>
    );
}

/**
 * How the job's flaky runs changed from the period before, or that it wasn't flaky then.
 */
function RunsChange({ job }: { job: FlakyJob }) {
    if (job.previousRuns === 0) {
        return (
            <Tooltip content="Not flaky the period before">
                <span className="shrink-0 rounded-full bg-bad-soft px-1.5 py-0.5 text-[11px] font-medium text-bad">
                    new
                </span>
            </Tooltip>
        );
    }

    return (
        <DeltaPill
            metric={{
                value: job.runs,
                previous: job.previousRuns,
                unit: 'count',
                better: 'lower',
            }}
        />
    );
}

/**
 * One of a job's figures, opening its metric over time for the job when it has a page.
 */
function Figure({
    label,
    hint,
    href,
    strong = false,
    className,
    children,
}: {
    label: string;
    hint?: string;
    href?: string;
    strong?: boolean;
    className?: string;
    children: ReactNode;
}) {
    const classes = cn(
        '-mx-2 w-24 shrink-0 rounded-md px-2 py-1 text-right',
        className,
    );
    const figure = (
        <>
            {hint ? (
                <Tooltip
                    content={hint}
                    className="cursor-help text-[11px] text-ink-muted underline decoration-line-strong decoration-dotted underline-offset-2"
                >
                    {label}
                </Tooltip>
            ) : (
                <div className="text-[11px] text-ink-muted">{label}</div>
            )}
            <div className={cn('tabular text-sm', strong && 'font-semibold')}>
                {children}
            </div>
        </>
    );

    return href ? (
        <Link
            href={href}
            title={`${label} over time for this job`}
            className={cn(classes, 'relative z-10 transition hover:bg-line/60')}
        >
            {figure}
        </Link>
    ) : (
        <div className={classes}>{figure}</div>
    );
}
