<?php

namespace App\Http\Controllers\Settings;

use App\Enums\RepositorySyncStatus;
use App\GitHub\GitHubClient;
use App\GitHub\RateLimitResource;
use App\GitHub\RateLimits;
use App\GitHub\SyncProgress;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateLookbackRequest;
use App\Jobs\SyncRepository;
use App\Models\Repository;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

/**
 * An overview of what the GitHub syncs are doing and how long they will take.
 */
class SyncController extends Controller
{
    /**
     * Limits seen more recently than this, e.g. by a running sync, are shown without asking GitHub again.
     */
    private const int RateLimitsFreshForSeconds = 30;

    public function __construct(private GitHubClient $github, private RateLimits $rateLimits) {}

    public function index(): Response
    {
        $repositories = Repository::query()->with('sync')->get();

        $queue = $repositories
            ->filter(fn (Repository $repository): bool => $repository->sync?->status === RepositorySyncStatus::Queued && $repository->sync->isInProgress())
            ->sortBy(fn (Repository $repository) => $repository->sync->updated_at)
            ->pluck('id')
            ->values();

        return Inertia::render('settings/syncs', [
            'staleAfterMinutes' => config()->integer('houston.stale_after_minutes'),
            'defaultLookbackDays' => Repository::defaultLookbackDays(),
            'rateLimits' => fn (): array => $this->rateLimitsPayload(),
            'lookbackOptions' => UpdateLookbackRequest::Options,
            'repositories' => $repositories
                ->sortBy(fn (Repository $repository): array => [$this->rank($repository), $repository->fullName()])
                ->values()
                ->map(fn (Repository $repository): array => [
                    'id' => $repository->id,
                    'owner' => $repository->owner,
                    'name' => $repository->name,
                    'fullName' => $repository->fullName(),
                    'avatarUrl' => $repository->sync?->avatar_url,
                    'lookbackDays' => $repository->lookback_days,
                    'queuePosition' => ($position = $queue->search($repository->id)) === false ? null : $position + 1,
                    'sync' => (new SyncProgress($repository))->toArray(),
                ]),
        ]);
    }

    public function store(): RedirectResponse
    {
        Repository::query()->each(fn (Repository $repository) => SyncRepository::trigger($repository, 'sync all'));

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function rateLimitsPayload(): array
    {
        $error = null;

        if (($this->rateLimits->observedAt()?->diffInSeconds(now()) ?? INF) > self::RateLimitsFreshForSeconds) {
            try {
                $this->github->rateLimits();
            } catch (Throwable $exception) {
                $error = $exception->getMessage();
            }
        }

        $known = $this->rateLimits->all();

        return [
            'reserve' => config()->integer('houston.github_rate_limit_reserve'),
            'observedAt' => $this->rateLimits->observedAt()?->toIso8601String(),
            'error' => $error,
            'resources' => collect([RateLimitResource::Graphql, RateLimitResource::Core])
                ->filter(fn (RateLimitResource $resource): bool => isset($known[$resource->value]))
                ->map(fn (RateLimitResource $resource): array => [
                    'resource' => $resource,
                    'label' => $resource->label(),
                    'limit' => $known[$resource->value]['limit'],
                    'remaining' => $known[$resource->value]['remaining'],
                    'used' => $known[$resource->value]['used'],
                    'resetAt' => now()->setTimestamp($known[$resource->value]['reset'])->toIso8601String(),
                    'blockedUntil' => $this->rateLimits->blockedUntil($resource->value)?->toIso8601String(),
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * Running syncs first, then the queue, then failures, then everything else.
     */
    private function rank(Repository $repository): int
    {
        $sync = $repository->sync;

        return match (true) {
            $sync?->status === RepositorySyncStatus::Running && $sync->isInProgress() => 0,
            $sync?->status === RepositorySyncStatus::Queued && $sync->isInProgress() => 1,
            $sync?->status === RepositorySyncStatus::Failed => 2,
            default => 3,
        };
    }
}
