/**
 * Pick a "nice" axis maximum and tick step for a value range starting at zero.
 */
export function niceScale(
    max: number,
    tickCount = 4,
): { max: number; ticks: number[] } {
    if (!Number.isFinite(max) || max <= 0) {
        return { max: 1, ticks: [0, 1] };
    }

    const rawStep = max / tickCount;
    const magnitude = 10 ** Math.floor(Math.log10(rawStep));
    const residual = rawStep / magnitude;
    const step =
        (residual > 5 ? 10 : residual > 2 ? 5 : residual > 1 ? 2 : 1) *
        magnitude;
    const niceMax = Math.ceil(max / step) * step;
    const ticks: number[] = [];

    for (let tick = 0; tick <= niceMax + step / 2; tick += step) {
        ticks.push(tick);
    }

    return { max: niceMax, ticks };
}

const HOUR = 3600;
const DAY = 86400;

/**
 * Durations read best on human boundaries: minutes, hours, days.
 */
export function niceDurationScale(max: number): {
    max: number;
    ticks: number[];
} {
    if (!Number.isFinite(max) || max <= 0) {
        return { max: HOUR, ticks: [0, HOUR] };
    }

    const unit = max >= 2 * DAY ? DAY : max >= 2 * HOUR ? HOUR : 60;
    const scaled = niceScale(max / unit);

    return {
        max: scaled.max * unit,
        ticks: scaled.ticks.map((tick) => tick * unit),
    };
}
