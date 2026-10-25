<?php

use App\Metrics\Benchmarks;
use App\Metrics\Metric;
use App\Metrics\Metrics;
use App\Metrics\PullRequests\CycleTime;
use App\Metrics\Trend;
use App\Metrics\Trends;
use App\Metrics\Trends\ThroughputTrend;
use App\Models\PullRequest;
use App\Models\Repository;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Queue::fake();
    CarbonImmutable::setTestNow('2026-10-07 12:00:00');
});

it('defines every metric a report produces, once', function () {
    $keys = array_map(fn (string $class): string => (new $class)->key(), Metrics::Definitions);

    expect($keys)->toEqualCanonicalizing(Benchmarks::Metrics)
        ->and($keys)->toBe(array_values(array_unique($keys)));
});

it('describes how every metric is calculated', function (string $class) {
    $metric = new $class;

    expect($metric)->toBeInstanceOf(Metric::class)
        ->and(trim($metric->description()))->not->toBeEmpty();
})->with(Metrics::Definitions);

it('sends the descriptions along with the metrics', function () {
    $repository = Repository::factory()->synced()->create(['owner' => 'acme', 'name' => 'app']);
    PullRequest::factory()->for($repository)->lifecycle(CarbonImmutable::now()->subDays(3), respondedAfter: 2, mergedAfter: 30)->create();

    $this->get(route('repositories.show', ['owner' => 'acme', 'name' => 'app']))
        ->assertInertia(fn (Assert $page) => $page->where('report.kpis.cycleTime.description', (new CycleTime)->description()));
});

it('describes every trend', function (string $class) {
    $trend = new $class;

    expect($trend)->toBeInstanceOf(Trend::class)
        ->and(trim($trend->title()))->not->toBeEmpty()
        ->and(trim($trend->description()))->not->toBeEmpty();
})->with(Trends::Definitions);

it('sends the trend descriptions along with the report', function () {
    $repository = Repository::factory()->synced()->create(['owner' => 'acme', 'name' => 'app']);
    PullRequest::factory()->for($repository)->lifecycle(CarbonImmutable::now()->subDays(3), respondedAfter: 2, mergedAfter: 30)->create();

    $this->get(route('repositories.show', ['owner' => 'acme', 'name' => 'app']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('report.trends.throughput.description', (new ThroughputTrend)->description())
            ->has('report.trends', count(Trends::Definitions)));
});
