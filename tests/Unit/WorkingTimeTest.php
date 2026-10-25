<?php

use App\Metrics\WorkingTime;
use Carbon\CarbonImmutable;

$at = fn (string $moment): CarbonImmutable => CarbonImmutable::parse($moment, 'UTC');

it('skips weekends', function () use ($at) {
    $weekdays = new WorkingTime('UTC', [1, 2, 3, 4, 5], '00:00', '24:00');

    // Friday 17:00 to Monday 09:00 is only 7h + 9h of weekday time.
    expect($weekdays->between($at('2026-10-02 17:00'), $at('2026-10-05 09:00')))->toBe(16 * 3600)
        ->and($weekdays->between($at('2026-10-03 10:00'), $at('2026-10-04 10:00')))->toBe(0);
});

it('only counts working hours', function () use ($at) {
    $office = new WorkingTime('UTC', [1, 2, 3, 4, 5], '09:00', '17:00');

    expect($office->between($at('2026-10-05 16:00'), $at('2026-10-06 10:00')))->toBe(2 * 3600)
        ->and($office->between($at('2026-10-05 18:00'), $at('2026-10-05 23:00')))->toBe(0)
        ->and($office->between($at('2026-10-05 00:00'), $at('2026-10-12 00:00')))->toBe(5 * 8 * 3600);
});

it('evaluates working hours in the configured timezone', function () use ($at) {
    $copenhagen = new WorkingTime('Europe/Copenhagen', [1, 2, 3, 4, 5], '09:00', '17:00');

    // 07:00-08:00 UTC is 09:00-10:00 in Copenhagen during summer time.
    expect($copenhagen->between($at('2026-10-05 07:00'), $at('2026-10-05 08:00')))->toBe(3600)
        ->and($copenhagen->between($at('2026-10-05 15:00'), $at('2026-10-05 16:00')))->toBe(0);
});

it('uses plain wall-clock time when every moment counts', function () use ($at) {
    $calendar = new WorkingTime('UTC', [1, 2, 3, 4, 5, 6, 7], '00:00', '24:00');

    expect($calendar->isCalendarTime())->toBeTrue()
        ->and($calendar->between($at('2026-10-03 10:00'), $at('2026-10-04 10:30')))->toBe(86400 + 1800)
        ->and($calendar->label())->toBe('Calendar time');
});

it('describes itself', function () {
    expect((new WorkingTime('UTC', [1, 2, 3, 4, 5], '00:00', '24:00'))->label())->toBe('Mon–Fri (UTC)')
        ->and((new WorkingTime('Europe/Copenhagen', [1, 2, 3, 4], '09:00', '17:00'))->label())->toBe('Mon–Thu 09:00–17:00 (Europe/Copenhagen)');
});

it('never returns negative durations', function () use ($at) {
    expect((new WorkingTime('UTC', [1, 2, 3, 4, 5], '00:00', '24:00'))->between($at('2026-10-06'), $at('2026-10-05')))->toBe(0);
});

it('counts long spans exactly by skipping whole weeks', function () use ($at) {
    $office = new WorkingTime('UTC', [1, 2, 3, 4, 5], '09:00', '17:00');

    // Compare against a straightforward day-by-day count across several full weeks.
    $from = $at('2026-09-02 12:00');
    $to = $at('2026-09-24 12:00');

    $expected = 0;
    for ($day = $from->startOfDay(); $day->lt($to); $day = $day->addDay()) {
        if ($day->isWeekday()) {
            $expected += max(0, min($day->setTime(17, 0)->getTimestamp(), $to->getTimestamp()) - max($day->setTime(9, 0)->getTimestamp(), $from->getTimestamp()));
        }
    }

    expect($office->between($from, $to))->toBe($expected)
        ->and($office->between($at('2023-01-02 00:00'), $at('2026-01-05 00:00')))->toBe(157 * 5 * 8 * 3600);
});
