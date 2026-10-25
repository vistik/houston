<?php

use App\Metrics\CiFailureRateScope;
use App\Metrics\Granularity;
use App\Models\Repository;
use App\Models\SystemEvent;
use App\Models\WorkflowRun;
use App\Settings\ReportDefaults;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Queue::fake();
});

it('opens reports on the last 30 days per day, with the CI failure rate on the default branch, until defaults are set', function () {
    $defaults = ['days' => 30, 'granularity' => 'day', 'withoutCurrentBucket' => false, 'ciFailureRateScope' => 'default_branch', 'timezone' => 'UTC'];

    expect(ReportDefaults::current()->toArray())->toBe($defaults);

    $this->get(route('settings.defaults.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/defaults')
            ->where('defaults', $defaults)
            ->where('timezones', fn ($timezones): bool => $timezones->contains('Europe/Copenhagen')));
});

it('saves the defaults', function () {
    $this->put(route('settings.defaults.update'), ['days' => 90, 'granularity' => 'week', 'withoutCurrentBucket' => true, 'ciFailureRateScope' => 'all_branches', 'timezone' => 'America/New_York'])
        ->assertRedirect(route('settings.defaults.edit'));

    expect(ReportDefaults::current()->toArray())->toBe(['days' => 90, 'granularity' => 'week', 'withoutCurrentBucket' => true, 'ciFailureRateScope' => 'all_branches', 'timezone' => 'America/New_York'])
        ->and(SystemEvent::query()->where('type', 'settings.defaults')->exists())->toBeTrue();

    $this->put(route('settings.defaults.update'), ['days' => null, 'granularity' => 'month', 'withoutCurrentBucket' => false, 'ciFailureRateScope' => 'default_branch', 'timezone' => 'UTC']);

    expect(ReportDefaults::current()->toArray())->toBe(['days' => null, 'granularity' => 'month', 'withoutCurrentBucket' => false, 'ciFailureRateScope' => 'default_branch', 'timezone' => 'UTC']);
});

it('rejects unknown periods, groupings, CI failure rate scopes and timezones', function () {
    $this->put(route('settings.defaults.update'), ['days' => 3, 'granularity' => 'fortnight', 'ciFailureRateScope' => 'feature_branches', 'timezone' => 'Mars/Olympus_Mons'])
        ->assertSessionHasErrors(['days', 'granularity', 'ciFailureRateScope', 'timezone']);
});

it('keeps the timezone when a page opened before it could be picked saves', function () {
    (new ReportDefaults(timezone: 'America/New_York'))->save();

    $this->put(route('settings.defaults.update'), ['days' => 30, 'granularity' => 'day', 'withoutCurrentBucket' => false, 'ciFailureRateScope' => 'default_branch'])
        ->assertSessionHasNoErrors();

    expect(ReportDefaults::current()->timezone)->toBe('America/New_York');
});

it('shows reports in UTC until a timezone is picked', function () {
    expect(ReportDefaults::current()->timezone)->toBe('UTC');

    (new ReportDefaults(timezone: 'America/New_York'))->save();

    expect(ReportDefaults::current()->timezone)->toBe('America/New_York');
});

it('rebuilds cached reports in the timezone picked', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-24 12:00'));
    Repository::factory()->synced()->create(['owner' => 'acme', 'name' => 'app']);

    $this->get(route('repositories.show', ['owner' => 'acme', 'name' => 'app']))
        ->assertInertia(fn (Assert $page) => $page->where('report.period.to', '2026-10-24T12:00:00+00:00'));

    (new ReportDefaults(timezone: 'Europe/Copenhagen'))->save();

    $this->get(route('repositories.show', ['owner' => 'acme', 'name' => 'app']))
        ->assertInertia(fn (Assert $page) => $page->where('report.period.to', '2026-10-24T14:00:00+02:00'));
});

it('counts the runs the CI failure rate default picks, rebuilding cached reports when it changes', function () {
    $repository = Repository::factory()->synced()->create(['owner' => 'acme', 'name' => 'app']);
    WorkflowRun::factory()->for($repository)->at(now()->toImmutable()->subDays(2))->create();
    WorkflowRun::factory()->for($repository)->failed()->at(now()->toImmutable()->subDay())->create(['event' => 'pull_request', 'head_branch' => 'feature']);

    $this->get(route('repositories.show', ['owner' => 'acme', 'name' => 'app']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('report.ciFailureRateScope', 'default_branch')
            ->where('report.kpis.ciFailureRate.value', 0)
            ->where('report.kpis.ciFailureRate.description', fn (string $description): bool => str_contains($description, 'on the default branch outside pull requests')));

    (new ReportDefaults(ciFailureRateScope: CiFailureRateScope::AllBranches))->save();

    $this->get(route('repositories.show', ['owner' => 'acme', 'name' => 'app']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('report.ciFailureRateScope', 'all_branches')
            ->where('report.kpis.ciFailureRate.value', 0.5)
            ->where('report.kpis.ciFailureRate.description', fn (string $description): bool => str_contains($description, 'on any branch')));
});

it('opens reports on the defaults when the URL leaves them out', function () {
    Repository::factory()->synced()->create(['owner' => 'acme', 'name' => 'app']);
    (new ReportDefaults(90, Granularity::Week))->save();

    $this->get(route('home'))
        ->assertInertia(fn (Assert $page) => $page->where('filters.days', 90));

    $this->get(route('repositories.show', ['owner' => 'acme', 'name' => 'app']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.days', 90)
            ->where('filters.granularity', 'week')
            ->where('report.period.granularity', 'week'));

    $this->get(route('repositories.show', ['owner' => 'acme', 'name' => 'app', 'days' => 14, 'granularity' => 'day']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.days', 14)
            ->where('report.period.granularity', 'day'));
});

it('opens reports on all of the cached history when that is the default', function () {
    Repository::factory()->synced()->create(['owner' => 'acme', 'name' => 'app', 'lookback_days' => 120]);
    (new ReportDefaults(null, Granularity::Month))->save();

    $this->get(route('repositories.show', ['owner' => 'acme', 'name' => 'app']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.all', true)
            ->where('report.period.days', 120)
            ->where('report.period.granularity', 'month'));
});

it('leaves the current bucket out of the charts when that is the default, unless the URL says otherwise', function () {
    Repository::factory()->synced()->create(['owner' => 'acme', 'name' => 'app']);
    (new ReportDefaults(30, Granularity::Day, withoutCurrentBucket: true))->save();

    $this->get(route('repositories.show', ['owner' => 'acme', 'name' => 'app']))
        ->assertInertia(fn (Assert $page) => $page->where('filters.complete', true));

    $this->get(route('repositories.show', ['owner' => 'acme', 'name' => 'app', 'complete' => 0]))
        ->assertInertia(fn (Assert $page) => $page->where('filters.complete', false));
});
