<?php

namespace App\Http\Controllers;

use App\Dependencies\DependencyOverview;
use App\Enums\SystemEventLevel;
use App\Enums\SystemEventType;
use App\Http\Controllers\Concerns\PresentsRepositories;
use App\Http\Controllers\Concerns\PresentsTimelineEvents;
use App\Http\Requests\StoreRepositoryRequest;
use App\Jobs\SyncRepository;
use App\Metrics\Period;
use App\Models\Repository;
use App\Models\SystemEvent;
use App\Models\TimelineEvent;
use App\Reports\ReportCache;
use App\Settings\OverviewLayouts;
use App\Settings\ReportDefaults;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RepositoryController extends Controller
{
    use PresentsRepositories;
    use PresentsTimelineEvents;

    public function __construct(private ReportCache $reports) {}

    public function index(Request $request): Response
    {
        $period = Period::fromRequest($request, lookbackDays: Repository::longestLookbackDays());

        $repositories = Repository::query()->with('sync')->orderBy('owner')->orderBy('name')->get();

        $repositories->filter->isStale()->each(fn (Repository $repository) => SyncRepository::trigger($repository, 'stale when the overview was viewed'));
        $repositories->load('sync');

        return Inertia::render('repositories/index', [
            'filters' => $period->filters(),
            'repositories' => $repositories->map(fn (Repository $repository): array => $this->repositoryPayload($repository)),
            'layouts' => OverviewLayouts::current()->toArray(),
            // Summaries can take a while to compute, so the page renders first and fills them in.
            'summaries' => Inertia::defer(fn (): array => $repositories
                ->filter(fn (Repository $repository): bool => $repository->sync?->pull_requests_synced_at !== null)
                ->mapWithKeys(fn (Repository $repository): array => [$repository->id => $this->reports->summary($repository, $period)])
                ->all()),
        ]);
    }

    public function store(StoreRepositoryRequest $request): RedirectResponse
    {
        $repository = Repository::create($request->coordinates());

        SystemEvent::record(SystemEventType::RepositoryAdded, "Started watching {$repository->fullName()}", $repository);

        SyncRepository::trigger($repository, 'newly watched');

        return to_route('repositories.show', ['owner' => $repository->owner, 'name' => $repository->name]);
    }

    public function show(Request $request, DependencyOverview $dependencies, string $owner, string $name): Response
    {
        $repository = Repository::query()->with('sync')->where('owner', $owner)->where('name', $name)->firstOrFail();
        $event = $this->focusedEvent($request);
        $period = Period::fromRequest($request, lookbackDays: $repository->lookbackDays(), since: $event?->happened_on)->withoutCurrentBucket($request->has('complete') ? $request->boolean('complete') : ReportDefaults::current()->withoutCurrentBucket)
            // Comparing with the time before an event charts both, side by side.
            ->chartingPrevious($event !== null);
        $includeBots = $request->boolean('bots');

        if ($repository->isStale()) {
            SyncRepository::trigger($repository, 'stale when viewed');
            $repository->load('sync');
        }

        return Inertia::render('repositories/show', [
            'repository' => $this->repositoryPayload($repository),
            'repositories' => Repository::query()->with('sync')->orderBy('owner')->orderBy('name')->get(['id', 'owner', 'name'])
                ->map(fn (Repository $watched): array => ['id' => $watched->id, 'owner' => $watched->owner, 'name' => $watched->name, 'fullName' => $watched->fullName(), 'avatarUrl' => $watched->sync?->avatar_url]),
            'filters' => [...$period->filters(), 'granularity' => $period->granularity, 'complete' => $period->withoutCurrentBucket, 'bots' => $includeBots, 'event' => $event?->id],
            'events' => TimelineEvent::payloads(),
            'event' => $this->focusedEventPayload($event, $repository->lookbackStart()),
            'report' => fn (): array => $this->reports->full($repository, $period, $includeBots),
            'dependencies' => fn (): ?array => $dependencies->for($repository),
            'view' => $repository->pageView()->value,
        ]);
    }

    public function destroy(Repository $repository): RedirectResponse
    {
        SystemEvent::record(SystemEventType::RepositoryRemoved, "Stopped watching {$repository->fullName()}", context: ['repository' => $repository->fullName()], level: SystemEventLevel::Warning);

        $repository->delete();

        // Removing a repository from its own page leaves nothing to go back to, but settings still list the rest.
        return strtok(url()->previous(), '?') === route('settings.syncs.index') ? to_route('settings.syncs.index') : to_route('repositories.index');
    }
}
