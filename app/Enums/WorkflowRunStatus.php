<?php

namespace App\Enums;

/**
 * Where a GitHub Actions workflow run is in its lifecycle.
 */
enum WorkflowRunStatus: string
{
    case Requested = 'requested';

    case Queued = 'queued';

    case Pending = 'pending';

    case Waiting = 'waiting';

    case InProgress = 'in_progress';

    case Completed = 'completed';

    /**
     * Read a status from the GitHub API. Statuses GitHub adds later count as pending, since only
     * completed runs have a verdict.
     */
    public static function fromGitHub(mixed $status): self
    {
        return is_string($status) ? self::tryFrom($status) ?? self::Pending : self::Pending;
    }
}
