<?php

use App\Models\PullRequest;
use App\Models\Repository;
use App\Models\WorkflowRun;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Queue::fake();
    $this->travelTo(now()->setTime(12, 0));
    $this->repository = Repository::factory()->synced()->create(['owner' => 'acme', 'name' => 'app']);
});

it('charts a pull request metric per bucket from the full report', function () {
    PullRequest::factory()->for($this->repository)->lifecycle(now()->toImmutable()->subDays(3), respondedAfter: 1, approvedAfter: 2, mergedAfter: 4)->create();

    $this->get(route('repositories.metric', ['owner' => 'acme', 'name' => 'app', 'metric' => 'throughput', 'days' => 7, 'complete' => 0]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('repositories/metric')
            ->where('metric', 'throughput')
            ->where('repository.fullName', 'acme/app')
            ->where('report.kpi.value', 1)
            ->has('report.kpi.description')
            ->has('report.dates', 7)
            ->has('report.values', 7)
            ->where('report.values', fn ($values) => array_sum($values->all()) === 1)
            ->where('report.metrics', fn ($metrics) => $metrics->contains('cycleTime') && $metrics->contains('ciDuration')));
});

it('charts both the median and the average per bucket for a metric that sums up one value per item', function () {
    // A Wednesday, so the pull requests are merged within working hours on a Monday.
    $this->travelTo('2026-10-21 12:00:00');
    $day = now()->toImmutable()->subDays(2)->setTime(9, 0);

    // Three pull requests merged the same day, after 1, 1 and 4 hours.
    foreach ([1, 1, 4] as $hours) {
        PullRequest::factory()->for($this->repository)->lifecycle($day, mergedAfter: $hours)->create();
    }

    $this->get(route('repositories.metric', ['owner' => 'acme', 'name' => 'app', 'metric' => 'cycleTime', 'days' => 7, 'complete' => 0]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('report.kpi.statistic', 'median')
            ->has('report.median', 7)
            ->has('report.average', 7)
            ->where('report.values', fn ($values) => collect($values)->filter()->values()->all() === [3600])
            ->where('report.median', fn ($values) => collect($values)->filter()->values()->all() === [3600])
            ->where('report.average', fn ($values) => collect($values)->filter()->values()->all() === [7200]));
});

it('has no median or average for a count', function () {
    $this->get(route('repositories.metric', ['owner' => 'acme', 'name' => 'app', 'metric' => 'throughput']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('report.values')
            ->missing('report.median')
            ->missing('report.average'));
});

it('breaks a CI metric down per bucket, which the full report leaves out', function () {
    WorkflowRun::factory()->for($this->repository)->at(now()->toImmutable()->subDays(2), durationMinutes: 5)->create();
    WorkflowRun::factory()->for($this->repository)->at(now()->toImmutable()->subDay(), durationMinutes: 10)->create();

    $this->get(route('repositories.metric', ['owner' => 'acme', 'name' => 'app', 'metric' => 'ciDuration', 'days' => 7, 'complete' => 0]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('report.values', 7)
            ->where('report.values', fn ($values) => collect($values)->filter()->sort()->values()->all() === [300, 600]));
});

it('has no history for a metric only known right now', function () {
    $this->get(route('repositories.metric', ['owner' => 'acme', 'name' => 'app', 'metric' => 'stalePullRequests']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('report.values', null)
            ->has('report.kpi'));
});

it('returns not found for an unknown metric or repository', function () {
    $this->get(route('repositories.metric', ['owner' => 'acme', 'name' => 'app', 'metric' => 'vibes']))->assertNotFound();
    $this->get(route('repositories.metric', ['owner' => 'acme', 'name' => 'missing', 'metric' => 'throughput']))->assertNotFound();
});
