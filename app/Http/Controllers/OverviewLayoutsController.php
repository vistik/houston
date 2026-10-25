<?php

namespace App\Http\Controllers;

use App\Enums\OverviewLayout;
use App\Settings\OverviewLayouts;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OverviewLayoutsController extends Controller
{
    /**
     * Save the layout for teams, repositories or both. A kind left out keeps its current layout.
     */
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'teams' => ['sometimes', 'required', Rule::enum(OverviewLayout::class)],
            'repositories' => ['sometimes', 'required', Rule::enum(OverviewLayout::class)],
        ]);

        $current = OverviewLayouts::current();

        (new OverviewLayouts(
            isset($validated['teams']) ? OverviewLayout::from($validated['teams']) : $current->teams,
            isset($validated['repositories']) ? OverviewLayout::from($validated['repositories']) : $current->repositories,
        ))->save();

        return back();
    }
}
