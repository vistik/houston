<?php

use App\Enums\RepositorySyncPhase;
use App\Enums\RepositorySyncStatus;
use App\GitHub\RepositorySynchronizer;
use App\Jobs\SyncRepository;
use App\Models\Repository;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    CarbonImmutable::setTestNow('2026-10-07 12:00:00');
    $this->repository = Repository::factory()->create(['owner' => 'acme', 'name' => 'app']);
});

it('releases the sync back to the queue until the rate limit resets', function () {
    Http::fake(['api.github.com/graphql' => Http::response(['message' => 'API rate limit exceeded'], 403, [
        'x-ratelimit-resource' => 'graphql', 'x-ratelimit-limit' => '5000', 'x-ratelimit-remaining' => '0',
        'x-ratelimit-used' => '5000', 'x-ratelimit-reset' => (string) now()->addMinutes(10)->getTimestamp(),
    ])]);

    $job = (new SyncRepository($this->repository))->withFakeQueueInteractions();
    $job->handle(app(RepositorySynchronizer::class));

    $job->assertReleased(delay: 605);

    $state = $this->repository->sync->fresh();

    expect($state->status)->toBe(RepositorySyncStatus::Queued)
        ->and($state->rate_limited_until)->toEqual(now()->addMinutes(10))
        ->and($state->phase)->toBe(RepositorySyncPhase::Details)
        ->and($state->isInProgress())->toBeTrue();
});

it('does not queue another sync while one waits out a rate limit', function () {
    Queue::fake();
    $this->repository->syncState()->update(['status' => RepositorySyncStatus::Queued, 'rate_limited_until' => now()->addMinutes(50)]);
    $this->repository->sync->forceFill(['updated_at' => now()->subMinutes(40)])->saveQuietly();

    SyncRepository::trigger($this->repository);

    Queue::assertNothingPushed();
});
