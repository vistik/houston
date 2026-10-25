<?php

namespace App\Http\Controllers;

use App\Enums\RepositoryView;
use App\Models\Repository;
use App\Support\Demo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RepositoryViewController extends Controller
{
    /**
     * Save the view a repository's page opens in.
     */
    public function update(Request $request, Repository $repository): RedirectResponse
    {
        $validated = $request->validate([
            'view' => ['required', Rule::enum(RepositoryView::class)],
        ]);

        Demo::savePreference("repositories.{$repository->id}.view", $validated['view'], fn () => $repository->update(['view' => $validated['view']]));

        return back();
    }
}
