import { afterEach, describe, expect, it, vi } from 'vite-plus/test';
import type { Metric } from '@/types';
import {
    formatDate,
    formatDuration,
    formatDurationCompact,
    formatMetric,
    formatNumber,
    formatPercent,
    metricDelta,
    timeAgo,
} from './format';

const MINUTE = 60;
const HOUR = 3600;
const DAY = 86400;

describe('formatDuration', () => {
    it('shows a dash when there is no value', () => {
        expect(formatDuration(null)).toBe('—');
        expect(formatDuration(undefined)).toBe('—');
    });

    it('shows seconds below a minute', () => {
        expect(formatDuration(0)).toBe('0s');
        expect(formatDuration(59.4)).toBe('59s');
    });

    it('rounds to whole minutes below an hour', () => {
        expect(formatDuration(MINUTE)).toBe('1m');
        expect(formatDuration(89)).toBe('1m');
        expect(formatDuration(90)).toBe('2m');
    });

    it('shows hours and minutes below ten hours', () => {
        expect(formatDuration(HOUR)).toBe('1h');
        expect(formatDuration(HOUR + 29)).toBe('1h');
        expect(formatDuration(HOUR + 30)).toBe('1h 1m');
        expect(formatDuration(1.5 * HOUR)).toBe('1h 30m');
        expect(formatDuration(9 * HOUR + 59 * MINUTE)).toBe('9h 59m');
    });

    it('drops the minutes from ten hours on', () => {
        expect(formatDuration(10 * HOUR + 30 * MINUTE)).toBe('10h');
        expect(formatDuration(23 * HOUR + 59 * MINUTE)).toBe('23h');
    });

    it('shows days with one decimal below ten days', () => {
        expect(formatDuration(DAY)).toBe('1.0d');
        expect(formatDuration(1.5 * DAY)).toBe('1.5d');
    });

    it('rounds to whole days from ten days on', () => {
        expect(formatDuration(10 * DAY)).toBe('10d');
        expect(formatDuration(12.6 * DAY)).toBe('13d');
    });
});

describe('formatDurationCompact', () => {
    it('shows a dash when there is no value', () => {
        expect(formatDurationCompact(null)).toBe('—');
        expect(formatDurationCompact(undefined)).toBe('—');
    });

    it('shows at least one minute below an hour', () => {
        expect(formatDurationCompact(0)).toBe('1m');
        expect(formatDurationCompact(10)).toBe('1m');
        expect(formatDurationCompact(45 * MINUTE)).toBe('45m');
    });

    it('shows a single decimal hour value below ten hours', () => {
        expect(formatDurationCompact(HOUR)).toBe('1.0h');
        expect(formatDurationCompact(2.8 * HOUR)).toBe('2.8h');
    });

    it('rounds to whole hours from ten hours on', () => {
        expect(formatDurationCompact(10 * HOUR)).toBe('10h');
        expect(formatDurationCompact(15.4 * HOUR)).toBe('15h');
    });

    it('formats days like formatDuration', () => {
        expect(formatDurationCompact(DAY)).toBe('1.0d');
        expect(formatDurationCompact(3.1 * DAY)).toBe('3.1d');
    });
});

describe('formatPercent', () => {
    it('shows a dash when there is no value', () => {
        expect(formatPercent(null)).toBe('—');
        expect(formatPercent(undefined)).toBe('—');
    });

    it('shows zero without decimals', () => {
        expect(formatPercent(0)).toBe('0%');
    });

    it('keeps one decimal for small percentages', () => {
        expect(formatPercent(0.05)).toBe('5.0%');
        expect(formatPercent(0.0012)).toBe('0.1%');
    });

    it('rounds to whole percentages from ten percent on', () => {
        expect(formatPercent(0.1)).toBe('10%');
        expect(formatPercent(0.456)).toBe('46%');
        expect(formatPercent(1)).toBe('100%');
        expect(formatPercent(1.5)).toBe('150%');
    });

    it('rounds negative ratios to whole percentages', () => {
        expect(formatPercent(-0.05)).toBe('-5%');
    });
});

describe('formatNumber', () => {
    it('shows a dash when there is no value', () => {
        expect(formatNumber(null)).toBe('—');
        expect(formatNumber(undefined)).toBe('—');
    });

    it('keeps one decimal below ten', () => {
        expect(formatNumber(0)).toBe('0');
        expect(formatNumber(3.14159)).toBe('3.1');
        expect(formatNumber(9.96)).toBe('10');
    });

    it('rounds to whole numbers from ten on', () => {
        expect(formatNumber(12.5)).toBe('13');
        expect(formatNumber(1234)).toBe('1,234');
        expect(formatNumber(9999)).toBe('9,999');
    });

    it('uses compact notation from ten thousand on', () => {
        expect(formatNumber(10000)).toBe('10K');
        expect(formatNumber(12345)).toBe('12K');
        expect(formatNumber(2_400_000)).toBe('2M');
    });
});

describe('formatMetric', () => {
    it('formats durations, compactly when asked', () => {
        expect(formatMetric(1.5 * HOUR, 'duration')).toBe('1h 30m');
        expect(formatMetric(1.5 * HOUR, 'duration', true)).toBe('1.5h');
    });

    it('formats ratios as percentages', () => {
        expect(formatMetric(0.25, 'ratio')).toBe('25%');
    });

    it('formats per-day rates', () => {
        expect(formatMetric(2.5, 'perDay')).toBe('2.5/day');
        expect(formatMetric(null, 'perDay')).toBe('—');
        expect(formatMetric(undefined, 'perDay')).toBe('—');
    });

    it('formats counts and lines as numbers', () => {
        expect(formatMetric(1234, 'count')).toBe('1,234');
        expect(formatMetric(15000, 'lines')).toBe('15K');
        expect(formatMetric(null, 'lines')).toBe('—');
    });
});

describe('metricDelta', () => {
    const metric = (overrides: Partial<Metric>): Metric => ({
        value: 10,
        previous: 10,
        unit: 'count',
        better: 'higher',
        ...overrides,
    });

    it('returns null without both values', () => {
        expect(metricDelta(metric({ value: null }))).toBeNull();
        expect(metricDelta(metric({ previous: null }))).toBeNull();
    });

    it('is flat and neutral when nothing changed', () => {
        expect(metricDelta(metric({}))).toEqual({
            label: '0%',
            tone: 'neutral',
            direction: 'flat',
        });
        expect(metricDelta(metric({ value: 10 + 1e-10 }))?.direction).toBe(
            'flat',
        );
    });

    it('colours an increase by whether higher is better', () => {
        expect(metricDelta(metric({ value: 12 }))).toEqual({
            label: '20%',
            tone: 'good',
            direction: 'up',
        });
        expect(metricDelta(metric({ value: 12, better: 'lower' }))).toEqual({
            label: '20%',
            tone: 'bad',
            direction: 'up',
        });
    });

    it('colours a decrease by whether lower is better', () => {
        expect(
            metricDelta(
                metric({
                    value: HOUR,
                    previous: 2 * HOUR,
                    unit: 'duration',
                    better: 'lower',
                }),
            ),
        ).toEqual({ label: '50%', tone: 'good', direction: 'down' });
        expect(metricDelta(metric({ value: 5 }))?.tone).toBe('bad');
    });

    it('stays neutral for neutral metrics', () => {
        expect(
            metricDelta(metric({ value: 20, better: 'neutral' })),
        ).toMatchObject({ tone: 'neutral', direction: 'up' });
    });

    it('describes ratio changes in percentage points', () => {
        expect(
            metricDelta(metric({ value: 0.55, previous: 0.5, unit: 'ratio' })),
        ).toEqual({ label: '5.0 pts', tone: 'good', direction: 'up' });
        expect(
            metricDelta(metric({ value: 0.4, previous: 0.5, unit: 'ratio' })),
        ).toEqual({ label: '10.0 pts', tone: 'bad', direction: 'down' });
    });

    it('shows the absolute difference when the previous value was zero', () => {
        expect(metricDelta(metric({ value: 4, previous: 0 }))?.label).toBe('4');
        expect(
            metricDelta(
                metric({ value: 1.5 * HOUR, previous: 0, unit: 'duration' }),
            )?.label,
        ).toBe('1h 30m');
    });

    it('shows a multiple from three times the previous value', () => {
        expect(metricDelta(metric({ value: 29 }))?.label).toBe('190%');
        expect(metricDelta(metric({ value: 30 }))?.label).toBe('3.0×');
        expect(metricDelta(metric({ value: 99 }))?.label).toBe('9.9×');
        expect(metricDelta(metric({ value: 120 }))?.label).toBe('12×');
    });
});

describe('timeAgo', () => {
    afterEach(() => {
        vi.useRealTimers();
    });

    const now = new Date('2026-10-23T12:00:00Z');
    const ago = (seconds: number) =>
        new Date(now.getTime() - seconds * 1000).toISOString();

    it('says never without a timestamp', () => {
        expect(timeAgo(null)).toBe('never');
        expect(timeAgo(undefined)).toBe('never');
        expect(timeAgo('')).toBe('never');
    });

    it('says just now below 45 seconds', () => {
        vi.useFakeTimers();
        vi.setSystemTime(now);

        expect(timeAgo(ago(0))).toBe('just now');
        expect(timeAgo(ago(44))).toBe('just now');
        expect(timeAgo(ago(45))).toBe('45s ago');
    });

    it('formats older timestamps as a duration', () => {
        vi.useFakeTimers();
        vi.setSystemTime(now);

        expect(timeAgo(ago(2 * HOUR))).toBe('2h ago');
        expect(timeAgo(ago(3 * DAY))).toBe('3.0d ago');
    });
});

describe('formatDate', () => {
    it('shows the month and day', () => {
        expect(formatDate('2026-10-06')).toBe('Oct 6');
    });

    it('adds the year when asked', () => {
        expect(formatDate('2026-10-06', true)).toBe('Oct 6, 2026');
    });

    it('shows moments in the timezone given', () => {
        expect(
            formatDate('2026-10-03T02:00:00Z', false, 'America/New_York'),
        ).toBe('Oct 2');
        expect(formatDate('2026-10-03T02:00:00Z', false, 'UTC')).toBe('Oct 3');
    });
});
