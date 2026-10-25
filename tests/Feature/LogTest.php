<?php

use App\Enums\RepositorySyncPhase;
use App\Enums\SystemEventLevel;
use App\Enums\SystemEventType;
use App\GitHub\RepositorySynchronizer;
use App\Jobs\SyncRepository;
use App\Models\Repository;
use App\Models\SystemEvent;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    CarbonImmutable::setTestNow('2026-10-07 12:00:00');
    $this->repository = Repository::factory()->create(['owner' => 'acme', 'name' => 'app']);
});

it('logs why a sync was queued', function () {
    Queue::fake();

    $this->post(route('repositories.sync', $this->repository));

    $event = SystemEvent::sole();

    expect($event->type)->toBe('sync.queued')
        ->and($event->repository_id)->toBe($this->repository->id)
        ->and($event->context)->toBe(['reason' => 'sync now']);
});

it('logs a sync that waits out a rate limit', function () {
    Http::fake(['api.github.com/graphql' => Http::response(['message' => 'API rate limit exceeded'], 403, [
        'x-ratelimit-resource' => 'graphql', 'x-ratelimit-limit' => '5000', 'x-ratelimit-remaining' => '0',
        'x-ratelimit-used' => '5000', 'x-ratelimit-reset' => (string) now()->addMinutes(10)->getTimestamp(),
    ])]);

    (new SyncRepository($this->repository))->withFakeQueueInteractions()->handle(app(RepositorySynchronizer::class));

    expect(SystemEvent::query()->orderBy('id')->pluck('type')->all())->toBe(['sync.started', 'sync.rate_limited'])
        ->and(SystemEvent::query()->where('type', 'sync.rate_limited')->sole())
        ->level->toBe(SystemEventLevel::Warning)
        ->context->toMatchArray(['resource' => 'graphql', 'phase' => 'details']);
});

it('logs a failed sync with the phase it failed in', function () {
    $this->repository->syncState()->enterPhase(RepositorySyncPhase::WorkflowRuns);

    (new SyncRepository($this->repository))->failed(new RuntimeException('Boom'));

    expect(SystemEvent::sole())
        ->type->toBe('sync.failed')
        ->level->toBe(SystemEventLevel::Error)
        ->message->toBe('Sync of acme/app failed: Boom')
        ->context->toMatchArray(['phase' => 'workflow_runs', 'exception' => RuntimeException::class]);
});

it('logs flushing the cache', function () {
    $this->artisan('houston:flush', ['--force' => true])->assertSuccessful();

    expect(SystemEvent::sole()->type)->toBe('cache.flushed');
});

it('keeps the log of a repository that is no longer watched', function () {
    SystemEvent::record(SystemEventType::SyncStarted, 'Started syncing acme/app', $this->repository);

    $this->delete(route('repositories.destroy', $this->repository));

    expect(SystemEvent::query()->orderBy('id')->get())
        ->sequence(
            fn ($event) => $event->repository_id->toBeNull(),
            fn ($event) => $event->type->toBe('repository.removed'),
        );
});

it('shows the latest events first and filters them', function () {
    $other = Repository::factory()->create();
    SystemEvent::record(SystemEventType::SyncStarted, 'Started syncing acme/app', $this->repository);
    SystemEvent::record(SystemEventType::SyncFailed, 'Sync of acme/app failed', $this->repository, ['phase' => 'details'], SystemEventLevel::Error);
    SystemEvent::record(SystemEventType::CacheWarmed, 'Warmed', $other);

    $this->get(route('settings.log.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/log')
            ->where('categories', ['cache', 'sync'])
            ->has('events.data', 3)
            ->where('events.data.0.type', 'cache.warmed')
            ->where('events.data.1.context', ['phase' => 'details']));

    $this->get(route('settings.log.index', ['level' => 'error']))
        ->assertInertia(fn (Assert $page) => $page->has('events.data', 1)->where('events.data.0.type', 'sync.failed'));

    $this->get(route('settings.log.index', ['category' => 'sync', 'repository' => $this->repository->id]))
        ->assertInertia(fn (Assert $page) => $page->has('events.data', 2));
});

it('shows 50 events per page', function () {
    foreach (range(1, 51) as $number) {
        SystemEvent::record(SystemEventType::SyncStarted, "Event {$number}");
    }

    $this->get(route('settings.log.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('events.data', 50)
            ->where('events.lastPage', 2)
            ->where('events.data.0.message', 'Event 51'));

    $this->get(route('settings.log.index', ['page' => 2]))
        ->assertInertia(fn (Assert $page) => $page->has('events.data', 1)->where('events.data.0.message', 'Event 1'));
});

it('prunes old events', function () {
    SystemEvent::record(SystemEventType::SyncStarted, 'Old')->forceFill(['created_at' => now()->subDays(SystemEvent::KeepDays + 1)])->save();
    SystemEvent::record(SystemEventType::SyncStarted, 'New');

    $this->artisan('model:prune', ['--model' => [SystemEvent::class]]);

    expect(SystemEvent::query()->pluck('message')->all())->toBe(['New']);
});
