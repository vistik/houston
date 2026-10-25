<?php

namespace App\Http\Controllers\Settings;

use App\Enums\SystemEventLevel;
use App\Enums\SystemEventType;
use App\Http\Controllers\Concerns\PresentsTeams;
use App\Http\Controllers\Controller;
use App\Http\Requests\TeamRequest;
use App\Models\PullRequest;
use App\Models\SystemEvent;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class TeamController extends Controller
{
    use PresentsTeams;

    public function index(): Response
    {
        return Inertia::render('settings/teams', [
            'teams' => Team::query()->with('memberships')->orderBy('name')->get()->map(fn (Team $team): array => $this->teamPayload($team)),
            // Everyone who authored a cached pull request, to pick members from.
            'authors' => PullRequest::query()->where('author_is_bot', false)->whereNotNull('author')->distinct()->orderBy('author')->pluck('author'),
        ]);
    }

    public function store(TeamRequest $request): RedirectResponse
    {
        $team = Team::create(['name' => $request->name(), 'slug' => Team::uniqueSlug($request->name())]);

        SystemEvent::record(SystemEventType::TeamCreated, "Created the team {$team->name}");

        return to_route('settings.teams.index');
    }

    public function update(TeamRequest $request, Team $team): RedirectResponse
    {
        $previous = $team->name;

        $team->update(['name' => $request->name(), 'slug' => Team::uniqueSlug($request->name(), except: $team)]);

        SystemEvent::record(SystemEventType::TeamRenamed, "Renamed the team {$previous} to {$team->name}");

        return to_route('settings.teams.index');
    }

    public function destroy(Team $team): RedirectResponse
    {
        $team->delete();

        SystemEvent::record(SystemEventType::TeamDeleted, "Deleted the team {$team->name}", level: SystemEventLevel::Warning);

        return to_route('settings.teams.index');
    }
}
