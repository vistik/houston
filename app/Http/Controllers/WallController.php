<?php

namespace App\Http\Controllers;

use App\Enums\RepositorySyncStatus;
use App\Jobs\SyncRepository;
use App\Metrics\Period;
use App\Models\Repository;
use App\Reports\ReportCache;
use App\Settings\ReportDefaults;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * A hands-off, auto-rotating view meant for a screen on the wall.
 */
class WallController extends Controller
{
    public function __construct(private ReportCache $reports) {}

    public function __invoke(Request $request): Response
    {
        $period = Period::lastDays($request->integer('days', 14), ReportDefaults::timezone());
        $only = $request->string('repos')->explode(',')->map(fn (string $name): string => trim($name))->filter();

        $repositories = Repository::query()
            ->with('sync')
            ->orderBy('owner')
            ->orderBy('name')
            ->get()
            ->when($only->isNotEmpty(), fn ($repositories) => $repositories->filter(fn (Repository $repository): bool => $only->contains($repository->fullName()) || $only->contains($repository->name)));

        $repositories->filter->isStale()->each(fn (Repository $repository) => SyncRepository::trigger($repository, 'stale when the wall was viewed'));
        $repositories->load('sync');

        return Inertia::render('wall', [
            'days' => $period->days,
            'rotateSeconds' => max(5, min(600, $request->integer('rotate', 20))),
            'repositories' => $repositories->values()->map(fn (Repository $repository): array => $this->slide($repository, $period)),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function slide(Repository $repository, Period $period): array
    {
        $sync = $repository->sync;

        return [
            'id' => $repository->id,
            'owner' => $repository->owner,
            'name' => $repository->name,
            'fullName' => $repository->fullName(),
            'avatarUrl' => $sync?->avatar_url,
            'defaultBranch' => $sync?->default_branch,
            'view' => $repository->pageView()->value,
            'cardView' => $repository->cardView()->value,
            'sync' => [
                'status' => $sync->status ?? RepositorySyncStatus::Pending,
                'hasData' => $sync?->pull_requests_synced_at !== null,
                'finishedAt' => $sync?->finished_at?->toIso8601String(),
            ],
            ...$this->reports->wall($repository, $period),
        ];
    }
}
