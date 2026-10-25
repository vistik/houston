<?php

namespace App\Http\Controllers\Settings;

use App\Enums\SystemEventLevel;
use App\Enums\SystemEventType;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTeamMembershipRequest;
use App\Http\Requests\UpdateTeamMembershipRequest;
use App\Models\SystemEvent;
use App\Models\Team;
use App\Models\TeamMembership;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class TeamMembershipController extends Controller
{
    /**
     * Put a login on the team from a given day. One already on another team leaves it that day.
     */
    public function store(StoreTeamMembershipRequest $request, Team $team): RedirectResponse
    {
        $joinedOn = $request->joinedOn();

        DB::transaction(function () use ($request, $team, $joinedOn): void {
            $ending = $request->membershipToEnd();

            if ($ending !== null) {
                $ending->update(['left_on' => $joinedOn]);

                SystemEvent::record(SystemEventType::TeamMemberLeft, "{$ending->login} left {$ending->team->name} on {$joinedOn?->toFormattedDateString()}");
            }

            $team->memberships()->create(['login' => $request->login(), 'joined_on' => $joinedOn]);
        });

        SystemEvent::record(SystemEventType::TeamMemberJoined, "{$request->login()} joined {$team->name}".($joinedOn ? " on {$joinedOn->toFormattedDateString()}" : ''));

        return to_route('settings.teams.index');
    }

    public function update(UpdateTeamMembershipRequest $request, TeamMembership $membership): RedirectResponse
    {
        $membership->update(['joined_on' => $request->day('joined_on'), 'left_on' => $request->day('left_on')]);

        SystemEvent::record(SystemEventType::TeamMemberUpdated, "{$membership->login} is on {$membership->team->name}{$membership->describeSpan()}");

        return to_route('settings.teams.index');
    }

    public function destroy(TeamMembership $membership): RedirectResponse
    {
        $membership->delete();

        SystemEvent::record(SystemEventType::TeamMemberRemoved, "Removed {$membership->login} from {$membership->team->name}", level: SystemEventLevel::Warning);

        return to_route('settings.teams.index');
    }
}
