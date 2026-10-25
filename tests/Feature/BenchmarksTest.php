<?php

use App\Metrics\Benchmarks;
use App\Metrics\BenchmarkTier;
use App\Metrics\Better;
use App\Metrics\Period;
use App\Models\Benchmark;
use App\Models\PullRequest;
use App\Models\Repository;
use App\Reports\VelocityReport;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Queue::fake();
    CarbonImmutable::setTestNow('2026-10-07 12:00:00');
});

it('covers every metric a report produces', function () {
    $repository = Repository::factory()->synced()->create();
    $kpis = (new VelocityReport($repository->fresh('sync'), Period::lastDays(7, 'UTC')))->toArray()['kpis'];

    expect(Benchmarks::Metrics)->toEqualCanonicalizing(array_keys($kpis))
        ->and(array_diff(array_keys(Benchmarks::Standards), Benchmarks::Metrics))->toBeEmpty()
        ->and(array_diff(Benchmarks::PerWeek, Benchmarks::Metrics))->toBeEmpty();
});

it('rates lower-is-better and higher-is-better metrics', function (float $value, Better $direction, BenchmarkTier $tier) {
    $thresholds = $direction === Better::Lower ? [10, 20, 30] : [30, 20, 10];

    expect(Benchmarks::tier($value, $direction, ...$thresholds))->toBe($tier);
})->with([
    [10, Better::Lower, BenchmarkTier::Elite],
    [15, Better::Lower, BenchmarkTier::Good],
    [30, Better::Lower, BenchmarkTier::Medium],
    [31, Better::Lower, BenchmarkTier::Low],
    [30, Better::Higher, BenchmarkTier::Elite],
    [25, Better::Higher, BenchmarkTier::Good],
    [10, Better::Higher, BenchmarkTier::Medium],
    [9, Better::Higher, BenchmarkTier::Low],
]);

it('rates period counts by their weekly rate', function () {
    $benchmark = new Benchmark(['metric' => 'throughput', 'direction' => Better::Higher, 'elite' => 10, 'good' => 5, 'medium' => 2]);

    expect(app(Benchmarks::class)->rate($benchmark, 18, 14))->toMatchArray(['tier' => BenchmarkTier::Good, 'perWeek' => true])
        ->and(app(Benchmarks::class)->rate($benchmark, 20, 7))->toMatchArray(['tier' => BenchmarkTier::Elite])
        ->and(app(Benchmarks::class)->rate($benchmark, null, 7))->toMatchArray(['tier' => null]);
});

it('rates benchmarked metrics on the repository page without waiting for the report cache', function () {
    $repository = Repository::factory()->synced()->create(['owner' => 'acme', 'name' => 'app']);
    PullRequest::factory()->for($repository)->lifecycle(CarbonImmutable::now()->subDays(3), respondedAfter: 2, mergedAfter: 30)->create();

    $this->get(route('repositories.show', ['owner' => 'acme', 'name' => 'app']))
        ->assertInertia(fn (Assert $page) => $page->missing('report.kpis.cycleTime.benchmark'));

    $this->put(route('settings.benchmarks.update'), ['benchmarks' => [
        'cycleTime' => ['direction' => 'lower', 'elite' => 25 * 3600, 'good' => 72 * 3600, 'medium' => 161 * 3600],
    ]]);

    $this->get(route('repositories.show', ['owner' => 'acme', 'name' => 'app']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('report.kpis.cycleTime.benchmark.tier', BenchmarkTier::Good)
            ->where('report.kpis.cycleTime.benchmark.elite', 25 * 3600)
            ->missing('report.kpis.pickupTime.benchmark'));
});

it('rates metrics on the overview and the wall', function () {
    $repository = Repository::factory()->synced()->create();
    Benchmark::create(['metric' => 'stalePullRequests', 'direction' => Better::Lower, 'elite' => 0, 'good' => 2, 'medium' => 5]);

    $this->get(route('home'))
        ->assertInertia(fn (Assert $page) => $page->loadDeferredProps(fn (Assert $reload) => $reload
            ->where("summaries.{$repository->id}.kpis.stalePullRequests.benchmark.tier", BenchmarkTier::Elite)));

    $this->get(route('wall'))
        ->assertInertia(fn (Assert $page) => $page->where('repositories.0.kpis.stalePullRequests.benchmark.tier', BenchmarkTier::Elite));
});

it('shows the benchmark settings with the standards', function () {
    Benchmark::create(['metric' => 'pickupTime', 'direction' => Better::Lower, 'elite' => 1800, 'good' => 3600, 'medium' => 7200]);

    $this->get(route('settings.benchmarks.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/benchmarks')
            ->where('benchmarks.pickupTime.elite', 1800)
            ->where('standards.changeFailureRate.source', 'DORA')
            ->where('perWeek', Benchmarks::PerWeek));
});

it('replaces the benchmarks with the submitted set', function () {
    Benchmark::create(['metric' => 'pickupTime', 'direction' => Better::Lower, 'elite' => 1800, 'good' => 3600, 'medium' => 7200]);

    $this->put(route('settings.benchmarks.update'), ['benchmarks' => [
        'deployFrequency' => ['direction' => 'higher', 'elite' => 1, 'good' => 0.5, 'medium' => 0.1],
        'reviewDepth' => ['direction' => 'higher', 'elite' => 3, 'good' => 2, 'medium' => 1],
    ]])->assertRedirect(route('settings.benchmarks.edit'));

    expect(Benchmark::pluck('metric')->all())->toEqualCanonicalizing(['deployFrequency', 'reviewDepth'])
        ->and(Benchmark::firstWhere('metric', 'deployFrequency')->good)->toBe(0.5);

    $this->put(route('settings.benchmarks.update'), ['benchmarks' => []])->assertSessionHasNoErrors();

    expect(Benchmark::count())->toBe(0);
});

it('rejects unknown metrics and thresholds out of order', function () {
    $this->put(route('settings.benchmarks.update'), ['benchmarks' => [
        'linesOfCode' => ['direction' => 'higher', 'elite' => 3, 'good' => 2, 'medium' => 1],
    ]])->assertSessionHasErrors('benchmarks');

    $this->put(route('settings.benchmarks.update'), ['benchmarks' => [
        'cycleTime' => ['direction' => 'lower', 'elite' => 7200, 'good' => 3600, 'medium' => 10800],
    ]])->assertSessionHasErrors('benchmarks.cycleTime');

    $this->put(route('settings.benchmarks.update'), ['benchmarks' => [
        'cycleTime' => ['direction' => 'lower', 'elite' => 3600, 'good' => 7200],
    ]])->assertSessionHasErrors('benchmarks.cycleTime.medium');

    expect(Benchmark::count())->toBe(0);
});
