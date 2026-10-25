<?php

namespace App\Http\Controllers;

use App\Settings\HomePins;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class HomePinsController extends Controller
{
    /**
     * Save the picks for each kind: null shows all of it, a list just those, and an empty list hides it.
     */
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'teams' => ['present', 'nullable', 'array'],
            'teams.*' => ['integer', 'distinct', 'exists:teams,id'],
            'repositories' => ['present', 'nullable', 'array'],
            'repositories.*' => ['integer', 'distinct', 'exists:repositories,id'],
        ]);

        (new HomePins(
            $this->ids($validated['teams']),
            $this->ids($validated['repositories']),
        ))->save();

        return back();
    }

    /**
     * @param  array<int|string>|null  $ids
     * @return list<int>|null
     */
    private function ids(?array $ids): ?array
    {
        return $ids === null ? null : array_values(array_map(intval(...), $ids));
    }
}
