<?php

namespace App\Http\Controllers\Concerns;

use App\Enums\RepositorySyncStatus;
use App\Models\Repository;

trait PresentsRepositories
{
    /**
     * @return array<string, mixed>
     */
    private function repositoryPayload(Repository $repository): array
    {
        $sync = $repository->sync;

        return [
            'id' => $repository->id,
            'owner' => $repository->owner,
            'name' => $repository->name,
            'fullName' => $repository->fullName(),
            'url' => "https://github.com/{$repository->fullName()}",
            'description' => $sync?->description,
            'avatarUrl' => $sync?->avatar_url,
            'defaultBranch' => $sync?->default_branch,
            'isPrivate' => $sync?->is_private,
            'excludedWorkflowIds' => $repository->excludedWorkflowIds(),
            'view' => $repository->pageView()->value,
            'cardView' => $repository->cardView()->value,
            // How far back the synced history reaches, so picking a period that starts earlier can offer to sync more.
            'lookback' => [
                'days' => $repository->lookbackDays(),
                'from' => $repository->lookbackStart()->toDateString(),
                'allTime' => $repository->hasAllTimeLookback(),
                // Where the cached data actually starts, which trails `from` until a backfill finishes.
                'coveredFrom' => $sync?->coveredFrom()?->toDateString(),
            ],
            'sync' => [
                'status' => $sync->status ?? RepositorySyncStatus::Pending,
                'inProgress' => $sync?->isInProgress() ?? false,
                'hasData' => $sync?->pull_requests_synced_at !== null,
                'finishedAt' => $sync?->finished_at?->toIso8601String(),
                'error' => $sync?->error,
            ],
        ];
    }
}
