<?php

use App\Http\Requests\UpdateLookbackRequest;
use App\Jobs\SyncRepository;
use App\Models\Repository;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Queue::fake();
});

it('queues a sync when the lookback reaches further back than the cache', function () {
    $repository = Repository::factory()->synced()->create();

    $this->put(route('repositories.lookback', $repository), ['lookback_days' => 365])->assertRedirect();

    expect($repository->fresh()->lookback_days)->toBe(365);
    Queue::assertPushed(SyncRepository::class);
});

it('queues a sync to fetch the whole history for an all-time lookback', function () {
    $repository = Repository::factory()->synced()->create();

    $this->put(route('repositories.lookback', $repository), ['lookback_days' => Repository::AllTime])->assertRedirect();

    expect($repository->fresh()->hasAllTimeLookback())->toBeTrue();
    Queue::assertPushed(SyncRepository::class);
});

it('keeps the cache when the lookback is shortened or reset to the default', function () {
    $repository = Repository::factory()->synced()->create();

    $this->put(route('repositories.lookback', $repository), ['lookback_days' => 30])->assertRedirect();
    $this->put(route('repositories.lookback', $repository), ['lookback_days' => null])->assertRedirect();

    expect($repository->fresh()->lookback_days)->toBeNull();
    Queue::assertNothingPushed();
});

it('only accepts the offered lookbacks', function () {
    $repository = Repository::factory()->synced()->create();

    $this->put(route('repositories.lookback', $repository), ['lookback_days' => 12])->assertSessionHasErrors('lookback_days');
    $this->put(route('repositories.lookback', $repository), [])->assertSessionHasErrors('lookback_days');

    expect($repository->fresh()->lookback_days)->toBeNull();
});

it('keeps custom ranges within the history of the repository', function () {
    Repository::factory()->synced()->create(['owner' => 'acme', 'name' => 'app', 'lookback_days' => 365]);

    $this->get(route('repositories.show', ['owner' => 'acme', 'name' => 'app', 'from' => '2020-01-01', 'to' => '2026-10-01']))
        ->assertInertia(fn ($page) => $page->where('filters.from', now()->subDays(365)->toDateString()));
});

it('tells the pages how far back the synced history of each repository reaches', function () {
    $this->travelTo('2026-10-20 15:00:00');
    Repository::factory()->synced()->create(['owner' => 'acme', 'name' => 'app', 'lookback_days' => 90]);
    Repository::factory()->synced()->create(['owner' => 'acme', 'name' => 'web']);

    $this->get(route('repositories.show', ['owner' => 'acme', 'name' => 'app']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('repository.lookback', ['days' => 90, 'from' => '2026-07-22', 'allTime' => false, 'coveredFrom' => '2026-04-23'])
            ->where('lookbackOptions', UpdateLookbackRequest::Options));

    $this->get(route('repositories.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('repositories.0.lookback', ['days' => 90, 'from' => '2026-07-22', 'allTime' => false, 'coveredFrom' => '2026-04-23'])
            ->where('repositories.1.lookback', ['days' => 180, 'from' => '2026-04-23', 'allTime' => false, 'coveredFrom' => '2026-04-23']));
});

it('tells the pages where the cached data starts while a backfill has yet to reach back', function () {
    $this->travelTo('2026-10-20 15:00:00');
    $repository = Repository::factory()->synced()->create(['owner' => 'acme', 'name' => 'app', 'lookback_days' => 365]);
    $repository->sync->update(['history_from' => '2026-04-23']);

    $this->get(route('repositories.show', ['owner' => 'acme', 'name' => 'app']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('repository.lookback.from', '2025-10-20')
            ->where('repository.lookback.coveredFrom', '2026-04-23'));
});

it('leaves out where the cached data starts before the first sync', function () {
    Repository::factory()->create(['owner' => 'acme', 'name' => 'app']);

    $this->get(route('repositories.show', ['owner' => 'acme', 'name' => 'app']))
        ->assertInertia(fn (Assert $page) => $page->where('repository.lookback.coveredFrom', null));
});

it('marks an all-time lookback, which no period reaches past', function () {
    Repository::factory()->synced()->create(['lookback_days' => Repository::AllTime]);

    $this->get(route('home'))
        ->assertInertia(fn (Assert $page) => $page->where('repositories.0.lookback.allTime', true));
});
