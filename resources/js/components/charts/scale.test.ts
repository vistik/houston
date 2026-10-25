import { describe, expect, it } from 'vite-plus/test';
import { niceDurationScale, niceScale } from './scale';

const MINUTE = 60;
const HOUR = 3600;
const DAY = 86400;

describe('niceScale', () => {
    it.each([0, -5, Number.NaN, Number.POSITIVE_INFINITY])(
        'falls back to a 0..1 scale for %s',
        (max) => {
            expect(niceScale(max)).toEqual({ max: 1, ticks: [0, 1] });
        },
    );

    it('steps by 1, 2, 5 or 10 times a power of ten', () => {
        expect(niceScale(4)).toEqual({ max: 4, ticks: [0, 1, 2, 3, 4] });
        expect(niceScale(7)).toEqual({ max: 8, ticks: [0, 2, 4, 6, 8] });
        expect(niceScale(10)).toEqual({ max: 10, ticks: [0, 5, 10] });
        expect(niceScale(23)).toEqual({ max: 30, ticks: [0, 10, 20, 30] });
        expect(niceScale(100)).toEqual({ max: 100, ticks: [0, 50, 100] });
    });

    it('handles fractional maxima', () => {
        expect(niceScale(1)).toEqual({ max: 1, ticks: [0, 0.5, 1] });

        const small = niceScale(0.3);

        expect(small.max).toBeCloseTo(0.3);
        expect(small.ticks).toHaveLength(4);
        expect(small.ticks[1]).toBeCloseTo(0.1);
    });

    it('respects the requested tick count', () => {
        expect(niceScale(10, 2)).toEqual({ max: 10, ticks: [0, 5, 10] });
        expect(niceScale(10, 10).ticks).toEqual([
            0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10,
        ]);
    });

    it.each([0.07, 1, 3, 9.5, 42, 99, 101, 1234, 987_654])(
        'covers %s with evenly spaced ticks from zero to the maximum',
        (max) => {
            const scale = niceScale(max);
            const step = scale.ticks[1] - scale.ticks[0];

            expect(scale.max).toBeGreaterThanOrEqual(max);
            expect(scale.ticks[0]).toBe(0);
            expect(scale.ticks.at(-1)).toBeCloseTo(scale.max);
            scale.ticks.forEach((tick, index) => {
                expect(tick).toBeCloseTo(index * step);
            });
        },
    );
});

describe('niceDurationScale', () => {
    it.each([0, -1, Number.NaN])('falls back to one hour for %s', (max) => {
        expect(niceDurationScale(max)).toEqual({
            max: HOUR,
            ticks: [0, HOUR],
        });
    });

    it('uses minutes below two hours', () => {
        expect(niceDurationScale(30 * MINUTE)).toEqual({
            max: 30 * MINUTE,
            ticks: [0, 10 * MINUTE, 20 * MINUTE, 30 * MINUTE],
        });
        expect(niceDurationScale(2 * HOUR - 1)).toEqual({
            max: 150 * MINUTE,
            ticks: [0, 50 * MINUTE, 100 * MINUTE, 150 * MINUTE],
        });
    });

    it('switches to hours at exactly two hours', () => {
        expect(niceDurationScale(2 * HOUR)).toEqual({
            max: 2 * HOUR,
            ticks: [0, 0.5 * HOUR, HOUR, 1.5 * HOUR, 2 * HOUR],
        });
        expect(niceDurationScale(5 * HOUR)).toEqual({
            max: 6 * HOUR,
            ticks: [0, 2 * HOUR, 4 * HOUR, 6 * HOUR],
        });
    });

    it('switches to days at exactly two days', () => {
        expect(niceDurationScale(2 * DAY)).toEqual({
            max: 2 * DAY,
            ticks: [0, 0.5 * DAY, DAY, 1.5 * DAY, 2 * DAY],
        });
        expect(niceDurationScale(10 * DAY)).toEqual({
            max: 10 * DAY,
            ticks: [0, 5 * DAY, 10 * DAY],
        });
    });
});
