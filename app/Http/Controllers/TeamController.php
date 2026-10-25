<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\PresentsTeams;
use App\Http\Controllers\Concerns\PresentsTimelineEvents;
use App\Metrics\Period;
use App\Models\Repository;
use App\Models\Team;
use App\Models\TimelineEvent;
use App\Reports\ReportCache;
use App\Settings\OverviewLayouts;
use App\Settings\ReportDefaults;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TeamController extends Controller
{
    use PresentsTeams;
    use PresentsTimelineEvents;

    public function __construct(private ReportCache $reports) {}

    public function index(Request $request): Response
    {
        $period = Period::fromRequest($request, lookbackDays: Repository::longestLookbackDays());
        $teams = Team::query()->with('memberships')->orderBy('name')->get();

        return Inertia::render('teams/index', [
            'filters' => $period->filters(),
            'teams' => $teams->map(fn (Team $team): array => $this->teamPayload($team)),
            'layouts' => OverviewLayouts::current()->toArray(),
            // Summaries read every repository, so the page renders first and fills them in.
            'summaries' => Inertia::defer(fn (): array => $teams
                ->mapWithKeys(fn (Team $team): array => [$team->id => $this->reports->teamSummary($team, $period)])
                ->all()),
        ]);
    }

    public function show(Request $request, Team $team): Response
    {
        $team->load('memberships');
        $event = $this->focusedEvent($request);
        $lookbackDays = Repository::longestLookbackDays();
        $period = Period::fromRequest($request, lookbackDays: $lookbackDays, since: $event?->happened_on)->withoutCurrentBucket($request->has('complete') ? $request->boolean('complete') : ReportDefaults::current()->withoutCurrentBucket)
            // Comparing with the time before an event charts both, side by side.
            ->chartingPrevious($event !== null);
        $counts = $this->reports->teamPullRequestsPerRepository($team, $period);
        $selected = $request->string('repository')->toString();

        // An unknown repository, or one the team has no pull requests in for the period, falls back to all of them.
        $repositories = Repository::query()->with('sync')->get()->keyBy(fn (Repository $repository): string => $repository->fullName());
        $repository = isset($counts[$selected]) ? $repositories->get($selected) : null;

        return Inertia::render('teams/show', [
            'team' => $this->teamPayload($team),
            'teams' => Team::query()->orderBy('name')->get(['id', 'name', 'slug'])
                ->map(fn (Team $other): array => ['id' => $other->id, 'name' => $other->name, 'slug' => $other->slug]),
            'repositories' => array_map(fn (string $fullName, int $count): array => [
                'fullName' => $fullName,
                'avatarUrl' => $repositories->get($fullName)?->sync?->avatar_url,
                'pullRequests' => $count,
            ], array_keys($counts), $counts),
            'filters' => [...$period->filters(), 'granularity' => $period->granularity, 'complete' => $period->withoutCurrentBucket, 'repository' => $repository?->fullName(), 'event' => $event?->id],
            'events' => TimelineEvent::payloads(),
            'event' => $this->focusedEventPayload($event, CarbonImmutable::now()->subDays($lookbackDays)->startOfDay()),
            'report' => fn (): array => $this->reports->team($team, $period, $repository),
        ]);
    }
}
