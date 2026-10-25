<?php

namespace App\Http\Controllers;

use App\Enums\TeamCardView;
use App\Models\Team;
use App\Support\Demo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TeamCardViewController extends Controller
{
    /**
     * Save what a team's card shows. It leaves the team's timestamp alone, since that versions its
     * cached reports and the card shows the same numbers either way.
     */
    public function update(Request $request, Team $team): RedirectResponse
    {
        $validated = $request->validate([
            'card_view' => ['required', Rule::enum(TeamCardView::class)],
        ]);

        Demo::savePreference("teams.{$team->id}.card_view", $validated['card_view'], function () use ($team, $validated): void {
            $team->timestamps = false;
            $team->update(['card_view' => $validated['card_view']]);
        });

        return back();
    }
}
