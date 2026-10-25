<?php

use App\Models\PullRequest;
use App\Models\Repository;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Queue::fake();
});

it('renders a slide per watched repository', function () {
    $repository = Repository::factory()->synced()->create(['owner' => 'acme', 'name' => 'app']);
    Repository::factory()->synced()->create(['owner' => 'acme', 'name' => 'api']);

    $waiting = PullRequest::factory()->for($repository)->lifecycle(now()->subDays(2))->create();
    PullRequest::factory()->for($repository)->lifecycle(now()->subDays(3))->create(['is_draft' => true]);

    $this->get(route('wall', ['rotate' => 45, 'days' => 7]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('wall')
            ->where('days', 7)
            ->where('rotateSeconds', 45)
            ->has('repositories', 2)
            ->where('repositories.1.fullName', 'acme/app')
            ->has('repositories.1.kpis.cycleTime')
            ->where('repositories.1.defaultBranchStatus.state', 'unknown')
            ->has('repositories.1.waitingForReview', 1)
            ->where('repositories.1.waitingForReview.0.number', $waiting->number));
});

it('can be limited to specific repositories', function () {
    Repository::factory()->synced()->create(['owner' => 'acme', 'name' => 'app']);
    Repository::factory()->synced()->create(['owner' => 'acme', 'name' => 'api']);

    $this->get(route('wall', ['repos' => 'acme/api']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('repositories', 1)
            ->where('repositories.0.fullName', 'acme/api')
            ->where('days', 14)
            ->where('rotateSeconds', 20));
});

it('keeps the rotation interval within sane bounds', function () {
    $this->get(route('wall', ['rotate' => 1]))
        ->assertInertia(fn (Assert $page) => $page->where('rotateSeconds', 5));
});
