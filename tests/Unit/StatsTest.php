<?php

use App\Metrics\Stats;

it('interpolates percentiles', function () {
    expect(Stats::median([1, 2, 3, 4]))->toBe(2.5)
        ->and(Stats::percentile([10, 20, 30, 40, 50], 90))->toBe(46.0)
        ->and(Stats::median([7]))->toBe(7.0)
        ->and(Stats::median([]))->toBeNull();
});

it('ignores nulls', function () {
    expect(Stats::median([null, 4, null, 2]))->toBe(3.0);
});

it('averages to one decimal, ignoring nulls', function () {
    expect(Stats::average([1, null, 2, 2]))->toBe(1.7)
        ->and(Stats::average([null]))->toBeNull();
});

it('returns null ratios for empty denominators', function () {
    expect(Stats::ratio(1, 0))->toBeNull()
        ->and(Stats::ratio(1, 4))->toBe(0.25);
});
