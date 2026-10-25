<?php

use App\Models\Repository;
use App\Models\Team;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Queue::fake();
});

it('shows the card that goes with the repository view until another card is picked', function () {
    Repository::factory()->synced()->create(['owner' => 'acme', 'name' => 'api']);
    Repository::factory()->synced()->create(['owner' => 'acme', 'name' => 'app', 'view' => 'open-source']);

    $this->get(route('repositories.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('repositories.0.cardView', 'full')
            ->where('repositories.1.cardView', 'open-source'));
});

it('remembers the card picked for a repository', function () {
    $repository = Repository::factory()->synced()->create(['owner' => 'acme', 'name' => 'app', 'view' => 'open-source']);

    $this->put(route('repositories.card-view', $repository), ['card_view' => 'delivery'])->assertRedirect();

    expect($repository->fresh()->cardView()->value)->toBe('delivery')
        ->and($repository->fresh()->pageView()->value)->toBe('open-source');

    $this->get(route('wall'))
        ->assertInertia(fn (Assert $page) => $page->where('repositories.0.cardView', 'delivery'));
});

it('remembers the card picked for a team without touching it', function () {
    $team = Team::factory()->create(['updated_at' => '2026-10-01 12:00:00']);

    $this->put(route('teams.card-view', $team), ['card_view' => 'review'])->assertRedirect();

    expect($team->fresh()->cardView()->value)->toBe('review')
        ->and($team->fresh()->updated_at->toDateTimeString())->toBe('2026-10-01 12:00:00');

    $this->get(route('teams.index'))
        ->assertInertia(fn (Assert $page) => $page->where('teams.0.cardView', 'review'));
});

it('rejects cards it does not know', function () {
    $repository = Repository::factory()->create();
    $team = Team::factory()->create();

    $this->put(route('repositories.card-view', $repository), ['card_view' => 'throughput'])->assertSessionHasErrors('card_view');
    $this->put(route('teams.card-view', $team), ['card_view' => 'delivery'])->assertSessionHasErrors('card_view');

    expect($repository->fresh()->card_view)->toBeNull()
        ->and($team->fresh()->card_view)->toBeNull();
});

it('falls back to the full card when the saved one is no longer known', function () {
    $repository = Repository::factory()->create(['card_view' => 'retired']);
    $team = Team::factory()->create(['card_view' => 'retired']);

    expect($repository->cardView()->value)->toBe('full')
        ->and($team->cardView()->value)->toBe('full');
});
