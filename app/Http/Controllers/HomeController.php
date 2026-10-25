<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\PresentsRepositories;
use App\Http\Controllers\Concerns\PresentsTeams;
use App\Jobs\SyncRepository;
use App\Metrics\Period;
use App\Models\Repository;
use App\Models\Team;
use App\Reports\ReportCache;
use App\Settings\HomePins;
use App\Settings\OverviewLayouts;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The front page: the teams and repositories picked to show there, each kind in its own section. A
 * kind with nothing picked has its section hidden.
 */
class HomeController extends Controller
{
    use PresentsRepositories;
    use PresentsTeams;

    public function __construct(private ReportCache $reports) {}

    public function __invoke(Request $request): Response
    {
        $period = Period::fromRequest($request, lookbackDays: Repository::longestLookbackDays());
        $pins = HomePins::current();

        $allRepositories = Repository::query()->with('sync')->orderBy('owner')->orderBy('name')->get();

        $allRepositories->filter->isStale()->each(fn (Repository $repository) => SyncRepository::trigger($repository, 'stale when the overview was viewed'));
        $allRepositories->load('sync');

        $allTeams = Team::query()->orderBy('name')->get(['id', 'name']);

        $repositories = $pins->showsAllRepositories() ? $allRepositories : $allRepositories->whereIn('id', $pins->repositoryIds)->values();
        $teams = Team::query()->with('memberships')->orderBy('name')->unless($pins->showsAllTeams(), fn (Builder $query) => $query->whereKey($pins->teamIds))->get();

        return Inertia::render('home', [
            'filters' => $period->filters(),
            // Teams only show once there are some, while an empty repositories section says how to add one.
            'sections' => ['teams' => $teams->isNotEmpty(), 'repositories' => $pins->repositoryIds !== []],
            'repositories' => $repositories->map(fn (Repository $repository): array => $this->repositoryPayload($repository)),
            'teams' => $teams->map(fn (Team $team): array => $this->teamPayload($team)),
            // Everything there is to pick from, and what is picked: null is all of a kind, an empty list none.
            'choices' => [
                'teams' => $allTeams->map(fn (Team $team): array => ['id' => $team->id, 'name' => $team->name]),
                'repositories' => $allRepositories->map(fn (Repository $repository): array => ['id' => $repository->id, 'name' => $repository->fullName(), 'avatarUrl' => $repository->sync?->avatar_url]),
            ],
            'pins' => $pins->toArray(),
            'layouts' => OverviewLayouts::current()->toArray(),
            // Summaries can take a while to compute, so the page renders first and fills them in, each kind on its own.
            // A hidden section has nothing picked, so nothing of it is computed.
            'summaries' => Inertia::defer(fn (): array => $repositories
                ->filter(fn (Repository $repository): bool => $repository->sync?->pull_requests_synced_at !== null)
                ->mapWithKeys(fn (Repository $repository): array => [$repository->id => $this->reports->summary($repository, $period)])
                ->all(), 'repositories'),
            'teamSummaries' => Inertia::defer(fn (): array => $teams
                ->mapWithKeys(fn (Team $team): array => [$team->id => $this->reports->teamSummary($team, $period)])
                ->all(), 'teams'),
        ]);
    }
}
