<?php

namespace App\Http\Controllers;

use App\Enums\RepositorySyncStatus;
use App\Jobs\SyncRepository;
use App\Metrics\Period;
use App\Models\Repository;
use App\Reports\ReportCache;
use App\Settings\ReportDefaults;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;

/**
 * A wall display for a single repository, rotating through one metric at a time.
 */
class RepositoryWallController extends Controller
{
    public function __construct(private ReportCache $reports) {}

    public function __invoke(Request $request, string $owner, string $name): Response
    {
        $repository = Repository::query()->with('sync')->where('owner', $owner)->where('name', $name)->firstOrFail();
        $period = Period::lastDays($request->integer('days', 30), ReportDefaults::timezone());

        if ($repository->isStale()) {
            SyncRepository::trigger($repository, 'stale when the wall was viewed');
            $repository->load('sync');
        }

        $sync = $repository->sync;

        return Inertia::render('repositories/wall', [
            'days' => $period->days,
            'rotateSeconds' => max(5, min(600, $request->integer('rotate', 20))),
            'repository' => [
                'id' => $repository->id,
                'owner' => $repository->owner,
                'name' => $repository->name,
                'fullName' => $repository->fullName(),
                'avatarUrl' => $sync?->avatar_url,
                'defaultBranch' => $sync?->default_branch,
                'view' => $repository->pageView()->value,
                'sync' => [
                    'status' => $sync->status ?? RepositorySyncStatus::Pending,
                    'hasData' => $sync?->pull_requests_synced_at !== null,
                    'finishedAt' => $sync?->finished_at?->toIso8601String(),
                ],
            ],
            'report' => fn (): array => $this->report($repository, $period),
        ]);
    }

    /**
     * The parts of the full report the slides show. It shares its cache with the repository page.
     *
     * @return array<string, mixed>
     */
    private function report(Repository $repository, Period $period): array
    {
        $report = $this->reports->full($repository, $period);

        return [
            ...Arr::only($report, ['period', 'kpis', 'trend', 'cycleBreakdown', 'ciFailureRateScope', 'defaultBranchStatus', 'delivery', 'workingTime']),
            'series' => Arr::only($report['series'], ['reviewTime', 'pullRequestSize']),
            // Stale pull requests are counted separately; the list is for work someone can pick up today.
            'waitingForReview' => collect(Arr::array($report, 'workInProgress'))
                ->filter(fn (array $pullRequest): bool => $pullRequest['status']->needsReview())
                ->where('isStale', false)
                ->sortByDesc('waitingSeconds')
                ->take(6)
                ->values()
                ->all(),
        ];
    }
}
