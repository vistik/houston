import { formatDuration, formatNumber, formatPercent } from '@/lib/format';
import type {
    Better,
    BenchmarkTier,
    Metric,
    MetricKey,
    MetricUnit,
    Thresholds,
} from '@/types';

export const TIERS: BenchmarkTier[] = ['elite', 'good', 'medium', 'low'];

export const TIER_LABELS: Record<BenchmarkTier, string> = {
    elite: 'Elite',
    good: 'Good',
    medium: 'Medium',
    low: 'Low',
};

export type MetricInfo = {
    label: string;
    hint: string;
    unit: MetricUnit;
    better: Better;
};

export const METRIC_GROUPS: {
    title: string;
    metrics: Partial<Record<MetricKey, MetricInfo>>;
}[] = [
    {
        title: 'Speed',
        metrics: {
            cycleTime: {
                label: 'Time to merge',
                hint: 'Opened → merged, median',
                unit: 'duration',
                better: 'lower',
            },
            pickupTime: {
                label: 'Pickup time',
                hint: 'Ready → first response',
                unit: 'duration',
                better: 'lower',
            },
            reviewTime: {
                label: 'Review time',
                hint: 'First response → merged',
                unit: 'duration',
                better: 'lower',
            },
            cycleTimeP90: {
                label: 'Time to merge (p90)',
                hint: 'The slow tail',
                unit: 'duration',
                better: 'lower',
            },
            leadTime: {
                label: 'Lead time',
                hint: 'First commit → merged',
                unit: 'duration',
                better: 'lower',
            },
            codingTime: {
                label: 'Coding time',
                hint: 'First commit → PR opened',
                unit: 'duration',
                better: 'lower',
            },
            timeToApproval: {
                label: 'Time to approval',
                hint: 'Ready → first approval',
                unit: 'duration',
                better: 'lower',
            },
            mergeDelay: {
                label: 'Merge delay',
                hint: 'Approval → merged',
                unit: 'duration',
                better: 'lower',
            },
            waitingOnReviewer: {
                label: 'Waiting on reviewers',
                hint: 'Per merged PR, median',
                unit: 'duration',
                better: 'lower',
            },
            waitingOnAuthor: {
                label: 'Waiting on the author',
                hint: 'After feedback, median',
                unit: 'duration',
                better: 'lower',
            },
            mergeFrequency: {
                label: 'Merge frequency',
                hint: 'Into the default branch',
                unit: 'perDay',
                better: 'higher',
            },
            throughput: {
                label: 'Merged PRs',
                hint: 'Merged in the period',
                unit: 'count',
                better: 'higher',
            },
            opened: {
                label: 'PRs opened',
                hint: 'New work started',
                unit: 'count',
                better: 'neutral',
            },
        },
    },
    {
        title: 'Review quality',
        metrics: {
            pullRequestSize: {
                label: 'PR size',
                hint: 'Median lines changed',
                unit: 'lines',
                better: 'lower',
            },
            reviewRounds: {
                label: 'Review rounds',
                hint: 'Times a PR went back to its author',
                unit: 'count',
                better: 'lower',
            },
            unreviewedMergeRate: {
                label: 'Merged without review',
                hint: 'No approving or commenting review',
                unit: 'ratio',
                better: 'lower',
            },
            changesRequestedRate: {
                label: 'Changes requested',
                hint: 'Share of merged PRs',
                unit: 'ratio',
                better: 'neutral',
            },
            reviewDepth: {
                label: 'Review depth',
                hint: 'Comments per merged PR',
                unit: 'count',
                better: 'neutral',
            },
            reworkRate: {
                label: 'Rework after review',
                hint: 'Reviewed PRs with follow-up pushes',
                unit: 'ratio',
                better: 'neutral',
            },
            reviewConcentration: {
                label: 'Review concentration',
                hint: 'Share of reviews by the busiest reviewer',
                unit: 'ratio',
                better: 'lower',
            },
            remediations: {
                label: 'Reverts & hotfixes',
                hint: 'Merged in the period',
                unit: 'count',
                better: 'lower',
            },
            mergeRate: {
                label: 'Merge rate',
                hint: 'Merged vs closed unmerged',
                unit: 'ratio',
                better: 'higher',
            },
        },
    },
    {
        title: 'CI health',
        metrics: {
            ciFailureRate: {
                label: 'CI failure rate',
                hint: 'Failed / finished runs',
                unit: 'ratio',
                better: 'lower',
            },
            ciDefaultBranchFailureRate: {
                label: 'Default branch failure rate',
                hint: 'Pushes to the default branch',
                unit: 'ratio',
                better: 'lower',
            },
            ciPullRequestFailureRate: {
                label: 'PR build failure rate',
                hint: 'pull_request runs',
                unit: 'ratio',
                better: 'lower',
            },
            defaultBranchRedShare: {
                label: 'Time the default branch was red',
                hint: 'Share of working time',
                unit: 'ratio',
                better: 'lower',
            },
            ciDuration: {
                label: 'CI duration',
                hint: 'Median run',
                unit: 'duration',
                better: 'lower',
            },
            ciDurationP90: {
                label: 'CI duration (p90)',
                hint: 'The slow tail',
                unit: 'duration',
                better: 'lower',
            },
            ciRecoveryTime: {
                label: 'Time to recovery',
                hint: 'Default branch red → green, median',
                unit: 'duration',
                better: 'lower',
            },
            ciQueueTime: {
                label: 'Queue time',
                hint: 'Waiting for a runner, median',
                unit: 'duration',
                better: 'lower',
            },
            ciFlakyRate: {
                label: 'Flaky commits',
                hint: 'Failed and passed on the same commit',
                unit: 'ratio',
                better: 'lower',
            },
            ciFlakyJobRuns: {
                label: 'Flaky job runs',
                hint: 'Failed, then passed on a re-run',
                unit: 'count',
                better: 'lower',
            },
            ciFlakyJobWait: {
                label: 'Waiting on flaky jobs',
                hint: 'Failed attempt → passing re-run',
                unit: 'duration',
                better: 'lower',
            },
            ciFlakyJobMinutes: {
                label: 'CI lost to flaky jobs',
                hint: 'Minutes of failed attempts',
                unit: 'count',
                better: 'lower',
            },
            ciRerunRate: {
                label: 'Re-run rate',
                hint: 'Runs retried at least once',
                unit: 'ratio',
                better: 'lower',
            },
            ciRuns: {
                label: 'Workflow runs',
                hint: 'All runs',
                unit: 'count',
                better: 'neutral',
            },
            ciMinutes: {
                label: 'CI minutes',
                hint: 'Wall clock, all runs',
                unit: 'count',
                better: 'neutral',
            },
        },
    },
    {
        title: 'Delivery',
        metrics: {
            deployFrequency: {
                label: 'Deploy frequency',
                hint: 'Deploys to production',
                unit: 'perDay',
                better: 'higher',
            },
            leadTimeToProduction: {
                label: 'Lead time to production',
                hint: 'First commit → shipped, median',
                unit: 'duration',
                better: 'lower',
            },
            changeFailureRate: {
                label: 'Change failure rate',
                hint: 'Deploys followed by a revert or hotfix',
                unit: 'ratio',
                better: 'lower',
            },
            timeToRestore: {
                label: 'Time to restore',
                hint: 'Failed change → next deploy, median',
                unit: 'duration',
                better: 'lower',
            },
            deploymentFailureRate: {
                label: 'Deployment failure rate',
                hint: 'Deployment attempts that failed',
                unit: 'ratio',
                better: 'lower',
            },
            deployments: {
                label: 'Deploys',
                hint: 'Deploys in the period',
                unit: 'count',
                better: 'higher',
            },
        },
    },
    {
        title: 'Work in progress',
        metrics: {
            openPullRequests: {
                label: 'Open PRs',
                hint: 'Right now',
                unit: 'count',
                better: 'neutral',
            },
            stalePullRequests: {
                label: 'Stale PRs',
                hint: 'Open without recent activity',
                unit: 'count',
                better: 'lower',
            },
        },
    },
    {
        title: 'People',
        metrics: {
            activeDevelopers: {
                label: 'Active developers',
                hint: 'Opened, merged or reviewed a PR',
                unit: 'count',
                better: 'neutral',
            },
        },
    },
];

const MINUTE = 60;
const HOUR = 60 * MINUTE;
const DAY = 24 * HOUR;
const WEEK = 7 * DAY;

const DURATION_UNITS: Record<string, number> = {
    w: WEEK,
    d: DAY,
    h: HOUR,
    m: MINUTE,
    s: 1,
};

/**
 * Whether thresholds are entered and shown as a weekly rate.
 */
export function isWeekly(unit: MetricUnit, perWeek: boolean): boolean {
    return perWeek || unit === 'perDay';
}

/**
 * One threshold as people read it: "25h", "5%", "1/wk".
 */
export function formatThreshold(
    value: number,
    unit: MetricUnit,
    perWeek: boolean,
): string {
    if (unit === 'duration') {
        return formatDuration(value);
    }

    if (unit === 'ratio') {
        return formatPercent(value);
    }

    if (isWeekly(unit, perWeek)) {
        return `${formatNumber(unit === 'perDay' ? value * 7 : value)}/wk`;
    }

    return formatNumber(value);
}

export function describeThresholds(
    thresholds: Thresholds,
    unit: MetricUnit,
    perWeek: boolean,
): string {
    const sign = thresholds.direction === 'higher' ? '≥' : '≤';

    return (['elite', 'good', 'medium'] as const)
        .map(
            (tier) =>
                `${TIER_LABELS[tier]} ${sign} ${formatThreshold(thresholds[tier], unit, perWeek)}`,
        )
        .join(' · ');
}

/**
 * The value a benchmark rates: per-week benchmarks rate a period's count by its weekly rate.
 */
export function ratedValue(
    value: number,
    perWeek: boolean,
    days: number,
): number {
    return perWeek ? (value * 7) / Math.max(1, days) : value;
}

/**
 * A metric's benchmark in the scale of one trend bucket: per-week thresholds become per-bucket counts.
 */
export function bucketThresholds(
    metric: Metric,
    bucketDays: number,
): Thresholds | undefined {
    const { benchmark } = metric;

    if (!benchmark) {
        return undefined;
    }

    const scale = benchmark.perWeek ? bucketDays / 7 : 1;

    return {
        direction: benchmark.direction,
        elite: benchmark.elite * scale,
        good: benchmark.good * scale,
        medium: benchmark.medium * scale,
    };
}

/**
 * The tier a rated value lands in, the same way the server rates it.
 */
export function tierFor(value: number, thresholds: Thresholds): BenchmarkTier {
    const meets = (threshold: number) =>
        thresholds.direction === 'higher'
            ? value >= threshold
            : value <= threshold;

    return (
        (['elite', 'good', 'medium'] as const).find((tier) =>
            meets(thresholds[tier]),
        ) ?? 'low'
    );
}

/**
 * What it takes to reach the next tier up, e.g. "1.2d away from Elite (≤ 25h)". Null at elite.
 */
export function nextTierGap(
    value: number,
    tier: BenchmarkTier,
    thresholds: Thresholds,
    unit: MetricUnit,
    perWeek: boolean,
): string | null {
    if (tier === 'elite') {
        return null;
    }

    const next = TIERS[TIERS.indexOf(tier) - 1] as Exclude<
        BenchmarkTier,
        'low'
    >;
    const gap = Math.abs(value - thresholds[next]);
    const sign = thresholds.direction === 'higher' ? '≥' : '≤';
    const amount =
        unit === 'ratio'
            ? `${formatNumber(gap * 100)} pts`
            : formatThreshold(gap, unit, perWeek);

    return `${amount} away from ${TIER_LABELS[next]} (${sign} ${formatThreshold(thresholds[next], unit, perWeek)})`;
}

/**
 * A stored threshold as an editable string, in the unit people type it in.
 */
export function toInput(
    value: number,
    unit: MetricUnit,
    perWeek: boolean,
): string {
    if (unit === 'duration') {
        const seconds = Math.round(value);
        const [suffix, size] = Object.entries(DURATION_UNITS).find(
            ([, size]) => seconds % size === 0,
        ) ?? ['s', 1];

        return `${seconds / size}${suffix}`;
    }

    if (unit === 'ratio') {
        return String(round(value * 100));
    }

    if (isWeekly(unit, perWeek) && unit === 'perDay') {
        return String(round(value * 7));
    }

    return String(round(value));
}

/**
 * Read a typed threshold back into the metric's unit, or null when it can't be read.
 * Durations accept "30m", "4h", "1d 12h"; a bare number is hours.
 */
export function fromInput(input: string, unit: MetricUnit): number | null {
    const text = input.trim().toLowerCase();

    if (text === '') {
        return null;
    }

    if (unit === 'duration') {
        if (!/^(\d+(\.\d+)?\s*[wdhms]?\s*)+$/.test(text)) {
            return null;
        }

        let seconds = 0;

        for (const [, amount, , suffix] of text.matchAll(
            /(\d+(\.\d+)?)\s*([wdhms]?)/g,
        )) {
            seconds += Number(amount) * DURATION_UNITS[suffix || 'h'];
        }

        return Math.round(seconds);
    }

    const number = Number(text.replace(/%$/, ''));

    if (!Number.isFinite(number) || number < 0) {
        return null;
    }

    if (unit === 'ratio') {
        return number / 100;
    }

    return unit === 'perDay' ? number / 7 : number;
}

export function inputSuffix(unit: MetricUnit, perWeek: boolean): string {
    if (unit === 'ratio') {
        return '%';
    }

    if (isWeekly(unit, perWeek)) {
        return '/wk';
    }

    return unit === 'lines' ? 'lines' : '';
}

function round(value: number): number {
    return Math.round(value * 1000) / 1000;
}
