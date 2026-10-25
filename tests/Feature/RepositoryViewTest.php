<?php

use App\Enums\RepositoryCardView;
use App\Enums\RepositoryView;
use App\Models\Repository;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Queue::fake();
    $this->repository = Repository::factory()->synced()->create(['owner' => 'acme', 'name' => 'app']);
    Repository::factory()->synced()->create(['owner' => 'acme', 'name' => 'api']);
});

it('shows the full view until another one is picked', function () {
    $this->get(route('repositories.show', ['owner' => 'acme', 'name' => 'app']))
        ->assertInertia(fn (Assert $page) => $page->where('view', 'full'));
});

it('remembers the picked view for that repository only', function () {
    $this->put(route('repositories.view', $this->repository), ['view' => 'open-source'])
        ->assertRedirect();

    $this->get(route('repositories.show', ['owner' => 'acme', 'name' => 'app']))
        ->assertInertia(fn (Assert $page) => $page->where('view', 'open-source'));

    $this->get(route('repositories.show', ['owner' => 'acme', 'name' => 'api']))
        ->assertInertia(fn (Assert $page) => $page->where('view', 'full'));
});

it('rejects views it does not know', function () {
    $this->put(route('repositories.view', $this->repository), ['view' => 'mosaic'])
        ->assertSessionHasErrors('view');

    expect($this->repository->fresh()->view)->toBeNull();
});

it('falls back to the full view when the saved one is no longer known', function () {
    $this->repository->update(['view' => 'retired']);

    $this->get(route('repositories.show', ['owner' => 'acme', 'name' => 'app']))
        ->assertInertia(fn (Assert $page) => $page->where('view', 'full'));
});

it('tells the front page and the walls which view each repository shows in', function () {
    $this->repository->update(['view' => 'open-source']);

    $this->get(route('repositories.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('repositories.0.view', 'full')
            ->where('repositories.1.view', 'open-source'));

    $this->get(route('wall'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('repositories.0.view', 'full')
            ->where('repositories.1.view', 'open-source'));

    $this->get(route('repositories.wall', ['owner' => 'acme', 'name' => 'app']))
        ->assertInertia(fn (Assert $page) => $page->where('repository.view', 'open-source'));
});

it('saves every view and gives each one a card to go with it', function (RepositoryView $view) {
    $this->put(route('repositories.view', $this->repository), ['view' => $view->value])->assertRedirect();

    expect($this->repository->fresh()->pageView())->toBe($view)
        ->and(RepositoryCardView::tryFrom($view->value))->toBe(RepositoryCardView::for($view));
})->with(RepositoryView::cases());
