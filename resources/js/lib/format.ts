import type { Metric, MetricUnit } from '@/types';

const MINUTE = 60;
const HOUR = 60 * MINUTE;
const DAY = 24 * HOUR;

export function formatDuration(seconds: number | null | undefined): string {
    if (seconds === null || seconds === undefined) {
        return '—';
    }

    if (seconds < MINUTE) {
        return `${Math.round(seconds)}s`;
    }

    if (seconds < HOUR) {
        return `${Math.round(seconds / MINUTE)}m`;
    }

    if (seconds < DAY) {
        const hours = Math.floor(seconds / HOUR);
        const minutes = Math.round((seconds % HOUR) / MINUTE);

        return minutes > 0 && hours < 10
            ? `${hours}h ${minutes}m`
            : `${hours}h`;
    }

    const days = seconds / DAY;

    return days < 10 ? `${days.toFixed(1)}d` : `${Math.round(days)}d`;
}

/**
 * A single-unit duration ("2.8h", "45m", "3.1d") for space-constrained, glanceable displays.
 */
export function formatDurationCompact(
    seconds: number | null | undefined,
): string {
    if (seconds === null || seconds === undefined) {
        return '—';
    }

    if (seconds < HOUR) {
        return `${Math.max(1, Math.round(seconds / MINUTE))}m`;
    }

    if (seconds < DAY) {
        const hours = seconds / HOUR;

        return hours < 10 ? `${hours.toFixed(1)}h` : `${Math.round(hours)}h`;
    }

    return formatDuration(seconds);
}

export function formatPercent(ratio: number | null | undefined): string {
    if (ratio === null || ratio === undefined) {
        return '—';
    }

    const percent = ratio * 100;

    return `${percent < 10 && percent > 0 ? percent.toFixed(1) : Math.round(percent)}%`;
}

export function formatNumber(value: number | null | undefined): string {
    if (value === null || value === undefined) {
        return '—';
    }

    return new Intl.NumberFormat('en', {
        maximumFractionDigits: value < 10 ? 1 : 0,
        notation: value >= 10000 ? 'compact' : 'standard',
    }).format(value);
}

export function formatMetric(
    value: number | null | undefined,
    unit: MetricUnit,
    compact = false,
): string {
    switch (unit) {
        case 'duration':
            return compact
                ? formatDurationCompact(value)
                : formatDuration(value);
        case 'ratio':
            return formatPercent(value);
        case 'perDay':
            return value === null || value === undefined
                ? '—'
                : `${formatNumber(value)}/day`;
        default:
            return formatNumber(value);
    }
}

export type Delta = {
    label: string;
    tone: 'good' | 'bad' | 'neutral';
    direction: 'up' | 'down' | 'flat';
};

/**
 * Describe the change against the previous period, coloured by whether the change is an improvement.
 */
export function metricDelta(metric: Metric): Delta | null {
    const { value, previous, unit, better } = metric;

    if (value === null || previous === null) {
        return null;
    }

    const difference = value - previous;
    const direction =
        Math.abs(difference) < 1e-9 ? 'flat' : difference > 0 ? 'up' : 'down';

    const tone =
        direction === 'flat' || better === 'neutral'
            ? 'neutral'
            : (direction === 'up') === (better === 'higher')
              ? 'good'
              : 'bad';

    let label: string;

    if (unit === 'ratio') {
        label = `${Math.abs(difference * 100).toFixed(1)} pts`;
    } else if (previous === 0) {
        label =
            unit === 'duration'
                ? formatDuration(Math.abs(difference))
                : formatNumber(Math.abs(difference));
    } else if (value / previous >= 3) {
        label = `${(value / previous).toFixed(value / previous >= 10 ? 0 : 1)}×`;
    } else {
        label = `${Math.round(Math.abs(difference / previous) * 100)}%`;
    }

    return { label, tone, direction };
}

export function timeAgo(iso: string | null | undefined): string {
    if (!iso) {
        return 'never';
    }

    const seconds = (Date.now() - new Date(iso).getTime()) / 1000;

    if (seconds < 45) {
        return 'just now';
    }

    return `${formatDuration(seconds)} ago`;
}

const DATE_ONLY = /^\d{4}-\d{2}-\d{2}$/;

/**
 * Month and day, e.g. "Oct 6". A date without a time, like a trend bucket's, is already a day in the
 * timezone reports are shown in, so it is shown as it is. A moment is shown in `timeZone`, or the
 * viewer's timezone without one.
 */
export function formatDate(
    value: string,
    withYear = false,
    timeZone?: string,
): string {
    // Date-only strings parse as midnight UTC, so read them back in UTC to keep the same day.
    return new Date(value).toLocaleDateString('en', {
        month: 'short',
        day: 'numeric',
        ...(withYear ? { year: 'numeric' } : {}),
        timeZone: DATE_ONLY.test(value) ? 'UTC' : timeZone,
    });
}
