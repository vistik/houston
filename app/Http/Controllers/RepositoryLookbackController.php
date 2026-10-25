<?php

namespace App\Http\Controllers;

use App\Enums\SystemEventType;
use App\Http\Requests\UpdateLookbackRequest;
use App\Jobs\SyncRepository;
use App\Models\Repository;
use App\Models\SystemEvent;
use Illuminate\Http\RedirectResponse;

class RepositoryLookbackController extends Controller
{
    /**
     * Change how far back a repository's history is cached. Reaching further back than the cache does
     * queues a sync to fetch the older history; a shorter lookback keeps what is already cached.
     */
    public function update(UpdateLookbackRequest $request, Repository $repository): RedirectResponse
    {
        $repository->update(['lookback_days' => $request->validated('lookback_days')]);

        $history = $repository->hasAllTimeLookback() ? 'all time' : "{$repository->lookbackDays()} days";

        SystemEvent::record(SystemEventType::SettingsLookback, "Changed the history of {$repository->fullName()} to {$history}", $repository, [
            'lookback_days' => $repository->lookback_days,
        ]);

        if ($repository->needsBackfill()) {
            SyncRepository::trigger($repository, 'history extended');
        }

        return back();
    }
}
