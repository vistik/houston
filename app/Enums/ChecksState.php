<?php

namespace App\Enums;

/**
 * The combined state of the status checks on a pull request's latest commit, as GitHub reports it.
 */
enum ChecksState: string
{
    case Success = 'SUCCESS';

    case Failure = 'FAILURE';

    case Error = 'ERROR';

    case Pending = 'PENDING';

    /** A required check that hasn't reported yet. */
    case Expected = 'EXPECTED';

    /**
     * Read a state from the GitHub API. Commits without checks have none, and so do states GitHub adds later.
     */
    public static function fromGitHub(mixed $state): ?self
    {
        return is_string($state) ? self::tryFrom($state) : null;
    }
}
