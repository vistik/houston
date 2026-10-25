<?php

namespace App\Enums;

/**
 * The latest status of a deployment, as GitHub reports it.
 */
enum DeploymentState: string
{
    case Success = 'success';

    case Failure = 'failure';

    case Error = 'error';

    case Pending = 'pending';

    case Queued = 'queued';

    case InProgress = 'in_progress';

    case Waiting = 'waiting';

    /** Superseded by a newer successful deployment. Cached as a success, since it went live. */
    case Inactive = 'inactive';

    /**
     * Read a state from the GitHub API, which spells them in upper case. States GitHub adds later count
     * as pending, so they neither succeed nor fail.
     */
    public static function fromGitHub(mixed $state): self
    {
        return is_string($state) ? self::tryFrom(strtolower($state)) ?? self::Pending : self::Pending;
    }

    public function failed(): bool
    {
        return $this === self::Failure || $this === self::Error;
    }
}
