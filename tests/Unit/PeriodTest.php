<?php

use App\Metrics\Granularity;
use App\Metrics\Period;
use App\Settings\ReportDefaults;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    $this->travelTo('2026-10-20 15:00:00');
});

it('covers whole days of a custom range, both ends included', function () {
    $period = Period::between(CarbonImmutable::parse('2026-10-01'), CarbonImmutable::parse('2026-10-07'), 'UTC');

    expect($period->custom)->toBeTrue()
        ->and($period->days)->toBe(7)
        ->and($period->from->toDateTimeString())->toBe('2026-10-01 00:00:00')
        ->and($period->to->toDateTimeString())->toBe('2026-10-08 00:00:00')
        ->and($period->previousFrom->toDateString())->toBe('2026-09-24')
        ->and($period->filters())->toBe(['days' => 7, 'from' => '2026-10-01', 'to' => '2026-10-07', 'all' => false])
        ->and($period->contains(CarbonImmutable::parse('2026-10-07 23:59:59')))->toBeTrue()
        ->and($period->contains(CarbonImmutable::parse('2026-10-08 00:00:00')))->toBeFalse();
});

it('swaps reversed ends and stops at today', function () {
    $period = Period::between(CarbonImmutable::parse('2026-12-01'), CarbonImmutable::parse('2026-10-18'), 'UTC');

    expect($period->filters())->toBe(['days' => 3, 'from' => '2026-10-18', 'to' => '2026-10-20', 'all' => false]);
});

it('keeps custom ranges within the cached history', function () {
    config(['houston.lookback_days' => 30]);

    $period = Period::between(CarbonImmutable::parse('2025-01-01'), CarbonImmutable::parse('2026-10-01'), 'UTC');

    expect($period->from->toDateString())->toBe('2026-09-20');
});

it('starts each charted bucket at midnight in the timezone it is shown in', function () {
    $period = Period::lastDays(7, 'America/New_York');

    expect($period->timezone)->toBe('America/New_York')
        // Today and the six days before it, from midnight in New York. Moments stay in the app's
        // timezone, as queries expect them.
        ->and($period->from->toDateTimeString())->toBe('2026-10-14 04:00:00')
        // Today is under way, so the previous window is as long as the time that has passed.
        ->and($period->previousFrom->toDateTimeString())->toBe('2026-10-07 17:00:00')
        ->and(array_map(fn (CarbonImmutable $start): string => $start->toDateTimeString(), $period->buckets()))->toBe([
            '2026-10-14 00:00:00', '2026-10-15 00:00:00', '2026-10-16 00:00:00', '2026-10-17 00:00:00',
            '2026-10-18 00:00:00', '2026-10-19 00:00:00', '2026-10-20 00:00:00',
        ])
        // Friday 22:00 in New York is Saturday in UTC, but charts on Friday.
        ->and($period->bucketIndex(CarbonImmutable::parse('2026-10-17 02:00:00')))->toBe(2)
        ->and($period->contains(CarbonImmutable::parse('2026-10-14 03:00:00')))->toBeFalse()
        ->and($period->charts(CarbonImmutable::parse('2026-10-14 04:00:00')))->toBeTrue();
});

it('keeps buckets on midnight across a daylight saving change', function () {
    $this->travelTo('2026-11-02 12:00:00');

    // Copenhagen goes from UTC+2 to UTC+1 on October 25.
    $period = Period::between(CarbonImmutable::parse('2026-10-22'), CarbonImmutable::parse('2026-10-28'), 'Europe/Copenhagen');

    expect($period->from->toDateTimeString())->toBe('2026-10-21 22:00:00')
        ->and($period->to->toDateTimeString())->toBe('2026-10-28 23:00:00')
        ->and($period->filters())->toBe(['days' => 7, 'from' => '2026-10-22', 'to' => '2026-10-28', 'all' => false])
        ->and($period->buckets()[4]->toDateTimeString())->toBe('2026-10-26 00:00:00')
        // 23:30 on October 26 in Copenhagen, more than four times 24 hours after the first midnight.
        ->and($period->bucketIndex(CarbonImmutable::parse('2026-10-26 22:30:00')))->toBe(4)
        ->and($period->bucketIndex(CarbonImmutable::parse('2026-10-26 23:00:00')))->toBe(5);
});

it('accepts the offered rolling periods and falls back to 30 days otherwise', function () {
    expect(Period::lastDays(45, 'UTC')->days)->toBe(45)
        ->and(Period::lastDays(44, 'UTC')->days)->toBe(30);
});

it('gives rolling and custom periods distinct cache keys', function () {
    expect(Period::lastDays(7, 'UTC')->key())->toBe('7@day')
        ->and(Period::between(CarbonImmutable::parse('2026-10-01'), CarbonImmutable::parse('2026-10-07'), 'UTC')->key())->toBe('2026-10-01..2026-10-08@day');
});

it('groups a period per day, week or month', function () {
    $quarter = Period::lastDays(90, 'UTC');

    expect($quarter->bucketGranularity())->toBe(Granularity::Week)
        ->and($quarter->groupedBy(Granularity::Day)->bucketDays())->toBe(1)
        ->and($quarter->groupedBy(Granularity::Month)->buckets())->toHaveCount(3)
        ->and($quarter->groupedBy(Granularity::Month)->bucketIndex(CarbonImmutable::now()->subDays(31)))->toBe(1)
        ->and(Period::lastDays(7, 'UTC')->groupedBy(Granularity::Week)->granularity)->toBeNull()
        ->and(Period::fromRequest(Request::create('/', parameters: ['days' => 30, 'granularity' => 'fortnight']), defaults: new ReportDefaults)->granularity)->toBeNull();
});

it('gives each granularity its own cache key', function () {
    $month = Period::lastDays(30, 'UTC');

    expect($month->groupedBy(Granularity::Week)->key())->toBe('30@week')
        ->and($month->groupedBy(Granularity::Day)->key())->toBe($month->key());
});

it('reaches back as far as the cached history for all time', function () {
    $period = Period::allTime('UTC', 180);

    expect($period->days)->toBe(180)
        ->and($period->from->toDateTimeString())->toBe('2026-04-24 00:00:00')
        ->and($period->filters())->toBe(['days' => 180, 'from' => null, 'to' => null, 'all' => true]);
});

it('leaves the current bucket out of the charts when asked', function () {
    $month = Period::lastDays(30, 'UTC')->groupedBy(Granularity::Week);
    $complete = $month->withoutCurrentBucket();

    expect($month->buckets())->toHaveCount(5)
        ->and($complete->buckets())->toHaveCount(4)
        ->and($complete->charts(CarbonImmutable::now()->subDay()))->toBeFalse()
        ->and($complete->charts(CarbonImmutable::now()->subDays(3)))->toBeTrue()
        ->and($complete->contains(CarbonImmutable::now()->subDay()))->toBeTrue()
        ->and($complete->key())->toBe('30@week-complete')
        ->and(Period::lastDays(7, 'UTC')->groupedBy(Granularity::Week)->withoutCurrentBucket()->groupedBy(Granularity::Day)->withoutCurrentBucket)->toBeTrue();
});

it('keeps at least one bucket when leaving out the current one', function () {
    $period = new Period(3, CarbonImmutable::now(), granularity: Granularity::Week, withoutCurrentBucket: true);

    expect($period->buckets())->toHaveCount(1);
});

it('charts the window before the period too when asked, without changing what it counts', function () {
    $period = Period::between(CarbonImmutable::parse('2026-10-11'), CarbonImmutable::parse('2026-10-20'), 'UTC')->chartingPrevious();

    expect($period->from->toDateString())->toBe('2026-10-11')
        ->and($period->chartFrom->toDateString())->toBe('2026-10-01')
        ->and($period->buckets())->toHaveCount(20)
        ->and($period->bucketIndex(CarbonImmutable::parse('2026-10-11 09:00')))->toBe(10)
        ->and($period->charts(CarbonImmutable::parse('2026-10-02')))->toBeTrue()
        ->and($period->contains(CarbonImmutable::parse('2026-10-02')))->toBeFalse()
        ->and($period->groupedBy(Granularity::Week)->withoutCurrentBucket()->chartsPrevious)->toBeTrue()
        ->and($period->key())->not->toBe($period->chartingPrevious(false)->key());
});

it('charts whole buckets before the period, so none straddles its start', function () {
    $period = Period::between(CarbonImmutable::parse('2026-08-21'), CarbonImmutable::parse('2026-10-20'), 'UTC')->groupedBy(Granularity::Week)->chartingPrevious();

    expect($period->days)->toBe(61)
        ->and($period->chartFrom->toDateString())->toBe('2026-06-26')
        ->and($period->bucketIndex($period->from))->toBe(8)
        ->and($period->from->diffInDays($period->buckets()[8]))->toEqual(0);
});
