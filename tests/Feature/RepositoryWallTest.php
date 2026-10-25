<?php

use App\Models\PullRequest;
use App\Models\Repository;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Queue::fake();
});

it('renders the metrics of a single repository', function () {
    $repository = Repository::factory()->synced()->create(['owner' => 'acme', 'name' => 'app']);
    Repository::factory()->synced()->create(['owner' => 'acme', 'name' => 'api']);

    $waiting = PullRequest::factory()->for($repository)->lifecycle(now()->subDays(2))->create();
    PullRequest::factory()->for($repository)->lifecycle(now()->subDays(3))->create(['is_draft' => true]);

    $this->get(route('repositories.wall', ['owner' => 'acme', 'name' => 'app', 'rotate' => 45, 'days' => 7]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('repositories/wall')
            ->where('days', 7)
            ->where('rotateSeconds', 45)
            ->where('repository.fullName', 'acme/app')
            ->has('report.kpis.cycleTime')
            ->has('report.kpis.reviewTime')
            ->has('report.trend', 7)
            ->has('report.series.reviewTime', 7)
            ->has('report.cycleBreakdown')
            ->where('report.defaultBranchStatus.state', 'unknown')
            ->has('report.waitingForReview', 1)
            ->where('report.waitingForReview.0.number', $waiting->number)
            ->missing('report.workInProgress')
            ->missing('report.authors'));
});

it('defaults to the last 30 days and keeps the rotation interval within sane bounds', function () {
    Repository::factory()->synced()->create(['owner' => 'acme', 'name' => 'app']);

    $this->get(route('repositories.wall', ['owner' => 'acme', 'name' => 'app', 'rotate' => 1]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('days', 30)
            ->where('rotateSeconds', 5));
});

it('returns not found for a repository that is not watched', function () {
    $this->get(route('repositories.wall', ['owner' => 'acme', 'name' => 'missing']))->assertNotFound();
});
