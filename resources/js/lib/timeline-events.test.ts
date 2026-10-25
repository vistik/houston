import { describe, expect, it } from 'vite-plus/test';
import { eventPositions } from './timeline-events';

const event = (date: string) => ({
    id: 1,
    title: 'Introduced AI reviews',
    date,
});

describe('eventPositions', () => {
    it('places an event on the day it happened within its bucket', () => {
        const dates = ['2026-10-01', '2026-10-08', '2026-10-15'];

        expect(eventPositions([event('2026-10-01')], dates, 7)).toEqual([
            { event: event('2026-10-01'), at: 0 },
        ]);
        expect(
            eventPositions([event('2026-10-11')], dates, 7)[0].at,
        ).toBeCloseTo(1 + 3 / 7);
    });

    it('starts daily buckets at the event', () => {
        const dates = ['2026-10-01', '2026-10-02', '2026-10-03'];

        expect(eventPositions([event('2026-10-03')], dates, 1)).toEqual([
            { event: event('2026-10-03'), at: 2 },
        ]);
    });

    it('leaves out events outside the charted buckets', () => {
        const dates = ['2026-10-01', '2026-10-08'];

        expect(
            eventPositions(
                [event('2026-09-30'), event('2026-10-15')],
                dates,
                7,
            ),
        ).toEqual([]);
    });
});
