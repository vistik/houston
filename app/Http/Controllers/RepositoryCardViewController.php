<?php

namespace App\Http\Controllers;

use App\Enums\RepositoryCardView;
use App\Models\Repository;
use App\Support\Demo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RepositoryCardViewController extends Controller
{
    /**
     * Save what a repository's card shows.
     */
    public function update(Request $request, Repository $repository): RedirectResponse
    {
        $validated = $request->validate([
            'card_view' => ['required', Rule::enum(RepositoryCardView::class)],
        ]);

        Demo::savePreference("repositories.{$repository->id}.card_view", $validated['card_view'], fn () => $repository->update(['card_view' => $validated['card_view']]));

        return back();
    }
}
