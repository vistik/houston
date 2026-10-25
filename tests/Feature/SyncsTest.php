<?php

use App\Enums\RepositorySyncPhase;
use App\Enums\RepositorySyncStatus;
use App\Jobs\SyncRepository;
use App\Models\Repository;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Queue::fake();
    CarbonImmutable::setTestNow('2026-10-07 12:00:00');

    Http::fake(['api.github.com/rate_limit' => Http::response(['resources' => [
        'core' => ['limit' => 5000, 'used' => 1000, 'remaining' => 4000, 'reset' => now()->addMinutes(30)->getTimestamp()],
        'graphql' => ['limit' => 5000, 'used' => 4980, 'remaining' => 20, 'reset' => now()->addMinutes(12)->getTimestamp()],
        'search' => ['limit' => 30, 'used' => 0, 'remaining' => 30, 'reset' => now()->addMinute()->getTimestamp()],
    ]])]);
});

it('shows the GitHub rate limits syncs use', function () {
    $this->get(route('settings.syncs.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('rateLimits.reserve', 50)
            ->has('rateLimits.resources', 2)
            ->where('rateLimits.resources.0', [
                'resource' => 'graphql', 'label' => 'GraphQL API', 'limit' => 5000, 'remaining' => 20, 'used' => 4980,
                'resetAt' => now()->addMinutes(12)->toIso8601String(),
                'blockedUntil' => now()->addMinutes(12)->toIso8601String(),
            ])
            ->where('rateLimits.resources.1.resource', 'core')
            ->where('rateLimits.resources.1.blockedUntil', null));
});

it('shows recently seen rate limits without asking GitHub again', function () {
    $this->get(route('settings.syncs.index'));
    $this->get(route('settings.syncs.index'));

    Http::assertSentCount(1);
});

it('shows where a sync paused for a rate limit and when it resumes', function () {
    $repository = Repository::factory()->synced()->create();
    $repository->sync->update([
        'status' => RepositorySyncStatus::Queued,
        'rate_limited_until' => now()->addMinutes(12),
        'phase' => RepositorySyncPhase::WorkflowRuns,
        'last_duration_seconds' => 60,
    ]);

    $this->get(route('settings.syncs.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('repositories.0.sync.inProgress', true)
            ->where('repositories.0.sync.rateLimitedUntil', now()->addMinutes(12)->toIso8601String())
            ->where('repositories.0.sync.phases.3.state', 'active')
            ->where('repositories.0.sync.estimatedFinishAt', now()->addMinutes(13)->toIso8601String()));
});

it('shows running syncs first with their progress and an estimate', function () {
    Repository::factory()->synced()->create(['owner' => 'acme', 'name' => 'done']);

    $running = Repository::factory()->create(['owner' => 'acme', 'name' => 'busy']);
    $running->sync()->create([
        'status' => RepositorySyncStatus::Running,
        'backfill' => true,
        'phase' => RepositorySyncPhase::PullRequests,
        'phase_started_at' => now()->subSeconds(50),
        'started_at' => now()->subMinute(),
        'pull_requests_resume_started_at' => now()->subMinute(),
        'pull_requests_from' => now()->subMinute()->subDays(100),
        'pull_requests_reached_at' => now()->subMinute()->subDays(50),
        'pull_requests_fetched' => 300,
    ]);

    $this->get(route('settings.syncs.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/syncs')
            ->has('repositories', 2)
            ->where('repositories.0.fullName', 'acme/busy')
            ->where('repositories.0.sync.phase', 'pull_requests')
            ->where('repositories.0.sync.phases.0.state', 'done')
            ->where('repositories.0.sync.phases.1.state', 'active')
            ->where('repositories.0.sync.phases.1.progress', 0.5)
            ->where('repositories.0.sync.phases.2.state', 'pending')
            // Details (0.02) plus half of the pull requests (0.3) are done.
            ->where('repositories.0.sync.progress', 0.32)
            ->where('repositories.0.sync.estimatedFinishAt', now()->subMinute()->addSeconds(188)->toIso8601String())
            ->where('repositories.0.sync.historyFrom', null)
            ->where('defaultLookbackDays', 180)
            ->where('repositories.1.fullName', 'acme/done')
            ->where('repositories.1.lookbackDays', null)
            ->where('repositories.1.sync.historyFrom', now()->subDays(180)->startOfDay()->toIso8601String())
            ->where('repositories.1.sync.phases.2.state', 'skipped')
            ->where('repositories.1.sync.phases.4.state', 'done'));
});

it('estimates incremental syncs from how long the last one took', function () {
    $repository = Repository::factory()->synced()->create();
    $repository->sync->update([
        'status' => RepositorySyncStatus::Running,
        'phase' => RepositorySyncPhase::Details,
        'started_at' => now()->subSeconds(10),
        'last_duration_seconds' => 40,
    ]);

    $this->get(route('settings.syncs.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('repositories.0.sync.backfill', false)
            ->where('repositories.0.sync.estimatedFinishAt', now()->addSeconds(30)->toIso8601String()));
});

it('numbers queued syncs in the order they were queued', function () {
    foreach (['second' => 1, 'first' => 2] as $name => $minutesAgo) {
        $repository = Repository::factory()->create(['owner' => 'acme', 'name' => $name]);
        $repository->sync()->create(['status' => RepositorySyncStatus::Queued])->forceFill(['updated_at' => now()->subMinutes($minutesAgo)])->saveQuietly();
    }

    $this->get(route('settings.syncs.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('repositories.0.fullName', 'acme/first')
            ->where('repositories.0.queuePosition', 1)
            ->where('repositories.1.queuePosition', 2));
});

it('flags running syncs that stopped making progress', function () {
    $repository = Repository::factory()->create();
    $repository->sync()->create(['status' => RepositorySyncStatus::Running, 'phase' => RepositorySyncPhase::WorkflowRuns, 'started_at' => now()->subMinutes(10)])
        ->forceFill(['updated_at' => now()->subMinutes(5)])->saveQuietly();

    $this->get(route('settings.syncs.index'))
        ->assertInertia(fn (Assert $page) => $page->where('repositories.0.sync.stalled', true));
});

it('shows workflow runs being fetched alongside the pull requests', function () {
    $repository = Repository::factory()->create();
    $repository->sync()->create([
        'status' => RepositorySyncStatus::Running,
        'phase' => RepositorySyncPhase::PullRequests,
        'backfill' => true,
        'started_at' => now()->subMinutes(2),
        'workflow_runs_from' => now()->subMinutes(2)->subDays(100),
        'workflow_runs_reached_at' => now()->subMinutes(2)->subDays(25),
    ]);

    $this->get(route('settings.syncs.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('repositories.0.sync.phases.1.state', 'active')
            ->where('repositories.0.sync.phases.3.state', 'active')
            ->where('repositories.0.sync.phases.3.progress', 0.75));

    $repository->sync->update(['workflow_runs_synced_at' => now()->subMinutes(2)]);

    $this->get(route('settings.syncs.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('repositories.0.sync.phases.1.state', 'active')
            ->where('repositories.0.sync.phases.3.state', 'done'));
});

it('shows deployments being fetched alongside the rest of the sync', function () {
    $repository = Repository::factory()->create();
    $repository->sync()->create([
        'status' => RepositorySyncStatus::Running,
        'phase' => RepositorySyncPhase::PullRequests,
        'started_at' => now()->subMinutes(2),
        'deployments_synced_at' => now()->subDay(),
    ]);

    $this->get(route('settings.syncs.index'))
        ->assertInertia(fn (Assert $page) => $page->where('repositories.0.sync.phases.4.state', 'active'));

    $repository->sync->update(['phase' => RepositorySyncPhase::WorkflowRuns, 'deployments_synced_at' => now()->subMinutes(2)]);

    $this->get(route('settings.syncs.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('repositories.0.sync.phases.3.state', 'active')
            ->where('repositories.0.sync.phases.4.state', 'done'));
});

it('shows the phase a failed sync stopped in', function () {
    $repository = Repository::factory()->create();
    $repository->sync()->create(['status' => RepositorySyncStatus::Failed, 'phase' => RepositorySyncPhase::WorkflowRuns, 'backfill' => true, 'error' => 'Boom']);

    $this->get(route('settings.syncs.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('repositories.0.sync.phases.1.state', 'done')
            ->where('repositories.0.sync.phases.3.state', 'failed')
            ->where('repositories.0.sync.error', 'Boom'));
});

it('queues a sync for every repository that is not already syncing', function () {
    Repository::factory()->synced()->count(2)->create();
    $busy = Repository::factory()->create();
    $busy->sync()->create(['status' => RepositorySyncStatus::Running]);

    $this->post(route('settings.syncs.store'))->assertRedirect();

    Queue::assertPushed(SyncRepository::class, 2);
});

it('opens the settings on the syncs tab', function () {
    $this->get(route('settings'))->assertRedirect(route('settings.syncs.index'));
});
