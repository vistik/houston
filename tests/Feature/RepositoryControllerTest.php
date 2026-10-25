<?php

use App\Enums\RepositorySyncStatus;
use App\Jobs\SyncRepository;
use App\Models\Dependency;
use App\Models\PullRequest;
use App\Models\Repository;
use App\Models\RepositorySync;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Queue::fake();
});

it('lists watched repositories and defers their summaries', function () {
    $repository = Repository::factory()->synced()->create(['owner' => 'acme', 'name' => 'app']);

    $this->get(route('repositories.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('repositories/index')
            ->where('filters', ['days' => 30, 'from' => null, 'to' => null, 'all' => false])
            ->has('repositories', 1, fn (Assert $repository) => $repository
                ->where('fullName', 'acme/app')
                ->missing('kpis')
                ->etc())
            ->missing('summaries')
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->has("summaries.{$repository->id}.kpis.throughput")
                ->has("summaries.{$repository->id}.trend", 30)));

    Queue::assertNothingPushed();
});

it('tells whether each repository is private once a sync has fetched it', function () {
    Repository::factory()->synced()->create(['owner' => 'acme', 'name' => 'app'])->sync->update(['is_private' => true]);
    Repository::factory()->synced()->create(['owner' => 'acme', 'name' => 'docs'])->sync->update(['is_private' => false]);
    Repository::factory()->create(['owner' => 'acme', 'name' => 'new']);

    $this->get(route('repositories.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('repositories.0.fullName', 'acme/app')
            ->where('repositories.0.isPrivate', true)
            ->where('repositories.1.fullName', 'acme/docs')
            ->where('repositories.1.isPrivate', false)
            ->where('repositories.2.fullName', 'acme/new')
            ->where('repositories.2.isPrivate', null));
});

it('leaves out summaries for repositories without data yet', function () {
    Repository::factory()->create(['owner' => 'acme', 'name' => 'app']);

    $this->get(route('repositories.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('repositories', 1)
            ->loadDeferredProps(fn (Assert $reload) => $reload->where('summaries', [])));
});

it('skips the report when polling the sync state', function () {
    Repository::factory()->synced()->create(['owner' => 'acme', 'name' => 'app']);

    $this->get(route('repositories.show', ['owner' => 'acme', 'name' => 'app']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('report.kpis')
            ->reloadOnly('repository', fn (Assert $reload) => $reload
                ->has('repository.sync')
                ->missing('report')));
});

it('serves repositories whose owner shares a name with an app page', function () {
    Repository::factory()->synced()->create(['owner' => 'teams', 'name' => 'app']);

    $this->get('/repos/teams/app')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('repositories/show')
            ->where('repository.fullName', 'teams/app'));
});

it('watches a repository given as a GitHub URL and queues a sync', function () {
    Http::fake(['api.github.com/repos/acme/app' => Http::response(['full_name' => 'acme/app'])]);

    $this->post(route('repositories.store'), ['repository' => 'https://github.com/acme/app.git'])
        ->assertRedirect(route('repositories.show', ['owner' => 'acme', 'name' => 'app']));

    $repository = Repository::sole();

    expect($repository->fullName())->toBe('acme/app')
        ->and($repository->sync->status)->toBe(RepositorySyncStatus::Queued);

    Queue::assertPushed(SyncRepository::class);
});

it('rejects repositories that do not exist on GitHub', function () {
    Http::fake(['api.github.com/repos/acme/missing' => Http::response(['message' => 'Not Found'], 404)]);

    $this->post(route('repositories.store'), ['repository' => 'acme/missing'])
        ->assertSessionHasErrors('repository');

    expect(Repository::count())->toBe(0);
});

it('rejects malformed and duplicate repositories', function () {
    Repository::factory()->create(['owner' => 'acme', 'name' => 'app']);

    $this->post(route('repositories.store'), ['repository' => 'not a repo'])->assertSessionHasErrors('repository');
    $this->post(route('repositories.store'), ['repository' => 'acme/app'])->assertSessionHasErrors('repository');

    expect(Repository::count())->toBe(1);
});

it('shows the velocity report and refreshes a stale cache', function () {
    $repository = Repository::factory()->create(['owner' => 'acme', 'name' => 'app']);

    $this->get(route('repositories.show', ['owner' => 'acme', 'name' => 'app', 'days' => 14, 'bots' => 1]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('repositories/show')
            ->where('repository.fullName', 'acme/app')
            ->where('repository.sync.inProgress', true)
            ->where('filters', ['days' => 14, 'from' => null, 'to' => null, 'all' => false, 'granularity' => 'day', 'complete' => false, 'bots' => true, 'event' => null])
            ->has('report.kpis.cycleTime')
            ->has('report.trend', 14));

    Queue::assertPushed(SyncRepository::class, fn (SyncRepository $job) => $job->repository->is($repository));
});

it('shows the velocity report for a custom date range', function () {
    $this->travelTo('2026-10-20 15:00:00');
    Repository::factory()->synced()->create(['owner' => 'acme', 'name' => 'app']);

    $this->get(route('repositories.show', ['owner' => 'acme', 'name' => 'app', 'from' => '2026-10-01', 'to' => '2026-10-10']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters', ['days' => 10, 'from' => '2026-10-01', 'to' => '2026-10-10', 'all' => false, 'granularity' => 'day', 'complete' => false, 'bots' => false, 'event' => null])
            ->where('report.period.custom', true)
            ->where('report.period.from', '2026-10-01T00:00:00+00:00')
            ->where('report.period.to', '2026-10-11T00:00:00+00:00')
            ->has('report.trend', 10));
});

it('falls back to the rolling period when the date range is invalid', function () {
    Repository::factory()->synced()->create(['owner' => 'acme', 'name' => 'app']);

    $this->get(route('repositories.index', ['from' => '2026-02-31', 'to' => '2026-03-10', 'days' => 7]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters', ['days' => 7, 'from' => null, 'to' => null, 'all' => false]));
});

it('charts the report per week or month when asked', function () {
    Repository::factory()->synced()->create(['owner' => 'acme', 'name' => 'app']);

    $this->get(route('repositories.show', ['owner' => 'acme', 'name' => 'app', 'days' => 90, 'granularity' => 'month']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.granularity', 'month')
            ->where('report.period.granularity', 'month')
            ->where('report.period.bucketDays', 30)
            ->has('report.trend', 3)
            ->has('report.series.throughput', 3));

    $this->get(route('repositories.show', ['owner' => 'acme', 'name' => 'app', 'days' => 14, 'granularity' => 'week']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('report.period.granularity', 'week')
            ->has('report.trend', 2));
});

it('reports on all of the cached history', function () {
    Repository::factory()->synced()->create(['owner' => 'acme', 'name' => 'app', 'lookback_days' => 120]);

    $this->get(route('repositories.show', ['owner' => 'acme', 'name' => 'app', 'days' => 'all']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.all', true)
            ->where('filters.days', 120)
            ->where('report.period.days', 120));
});

it('leaves the current week out of the charts when asked', function () {
    Repository::factory()->synced()->create(['owner' => 'acme', 'name' => 'app']);

    $this->get(route('repositories.show', ['owner' => 'acme', 'name' => 'app', 'days' => 30, 'granularity' => 'week', 'complete' => 1]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.complete', true)
            ->has('report.trend', 4)
            ->has('report.series.throughput', 4));
});

it('ignores granularities that leave fewer than two buckets', function () {
    Repository::factory()->synced()->create(['owner' => 'acme', 'name' => 'app']);

    $this->get(route('repositories.show', ['owner' => 'acme', 'name' => 'app', 'days' => 30, 'granularity' => 'month']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.granularity', null)
            ->where('report.period.granularity', 'day')
            ->has('report.trend', 30));
});

it('does not refresh a fresh cache when viewing it', function () {
    Repository::factory()->synced()->create(['owner' => 'acme', 'name' => 'app']);

    $this->get(route('repositories.show', ['owner' => 'acme', 'name' => 'app']))->assertOk();

    Queue::assertNothingPushed();
});

it('falls back to 30 days for unsupported periods', function () {
    Repository::factory()->synced()->create(['owner' => 'acme', 'name' => 'app']);

    $this->get(route('repositories.show', ['owner' => 'acme', 'name' => 'app', 'days' => 3650]))
        ->assertInertia(fn (Assert $page) => $page->where('filters.days', 30));
});

it('queues a manual refresh', function () {
    $repository = Repository::factory()->synced()->create();

    $this->post(route('repositories.sync', $repository))->assertRedirect();

    Queue::assertPushed(SyncRepository::class);
    expect($repository->sync->fresh()->status)->toBe(RepositorySyncStatus::Queued);
});

it('stops watching a repository and drops its cache', function () {
    $repository = Repository::factory()->synced()->create();
    PullRequest::factory()->for($repository)->create();

    $this->delete(route('repositories.destroy', $repository))->assertRedirect(route('repositories.index'));

    expect(Repository::count())->toBe(0)
        ->and(PullRequest::count())->toBe(0)
        ->and(RepositorySync::count())->toBe(0);
});

it('returns to the settings after removing a repository there', function () {
    $repository = Repository::factory()->create();

    $this->from(route('settings.syncs.index'))
        ->delete(route('repositories.destroy', $repository))
        ->assertRedirect(route('settings.syncs.index'));

    expect(Repository::count())->toBe(0);
});

it('re-queues a sync that was abandoned in the queue', function () {
    $repository = Repository::factory()->create();
    $repository->syncState()->update(['status' => RepositorySyncStatus::Queued]);
    RepositorySync::query()->update(['updated_at' => now()->subHour()]);

    $this->post(route('repositories.sync', $repository));

    Queue::assertPushed(SyncRepository::class);
});

it('recomputes the report once the cache has been refreshed', function () {
    $repository = Repository::factory()->synced()->create(['owner' => 'acme', 'name' => 'app']);
    PullRequest::factory()->for($repository)->lifecycle(now()->subDays(2), mergedAfter: 1)->create();

    $throughput = fn () => $this->get(route('repositories.show', ['owner' => 'acme', 'name' => 'app']))
        ->viewData('page')['props']['report']['kpis']['throughput']['value'];

    expect($throughput())->toBe(1);

    PullRequest::factory()->for($repository)->lifecycle(now()->subDay(), mergedAfter: 1)->create();
    expect($throughput())->toBe(1);

    $this->travel(1)->minute();
    $repository->sync->update(['finished_at' => now()]);
    expect($throughput())->toBe(2);
});

it('shows how far behind the dependencies are, furthest first', function () {
    $repository = Repository::factory()->synced()->create(['owner' => 'acme', 'name' => 'app']);
    Dependency::factory()->for($repository)->behind('1.2.0', '1.2.1')->create(['name' => 'acme/patch']);
    Dependency::factory()->for($repository)->behind('1.0.0', '2.0.0')->create(['name' => 'acme/major']);
    Dependency::factory()->for($repository)->behind('1.0.0', '1.0.0')->create(['name' => 'acme/current']);
    Dependency::factory()->for($repository)->behind('dev-main', '2.0.0')->create(['name' => 'acme/branch']);
    $repository->dependencySnapshots()->create(['recorded_on' => '2026-09-01', 'total' => 4, 'outdated' => 3, 'major' => 2, 'checked_at' => '2026-09-01 12:00:00']);
    $repository->dependencySnapshots()->create(['recorded_on' => '2026-09-20', 'total' => 4, 'outdated' => 1, 'major' => 0, 'checked_at' => '2026-09-20 12:00:00']);
    $repository->dependencySnapshots()->create(['recorded_on' => '2026-10-07', 'total' => 4, 'outdated' => 2, 'major' => 1, 'checked_at' => '2026-10-07 12:00:00']);

    $this->get(route('repositories.show', ['owner' => 'acme', 'name' => 'app']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('dependencies.total', 4)
            ->where('dependencies.outdated', 2)
            ->where('dependencies.major', 1)
            ->where('dependencies.unknown', 1)
            ->where('dependencies.byConstraint', 0)
            ->where('dependencies.earlier', ['recordedOn' => '2026-09-01', 'outdated' => 3, 'major' => 2])
            ->where('dependencies.packages', fn ($packages): bool => collect($packages)->pluck('name')->all() === ['acme/major', 'acme/patch', 'acme/branch', 'acme/current']));
});

it('shows that the dependencies were not checked yet', function () {
    Repository::factory()->synced()->create(['owner' => 'acme', 'name' => 'app']);

    $this->get(route('repositories.show', ['owner' => 'acme', 'name' => 'app']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('dependencies', null));
});
