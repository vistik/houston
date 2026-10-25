<?php

namespace App\Enums;

/**
 * How a completed GitHub Actions workflow run ended.
 */
enum WorkflowRunConclusion: string
{
    case Success = 'success';

    case Failure = 'failure';

    case TimedOut = 'timed_out';

    /** The workflow file itself was broken, so no job ran. */
    case StartupFailure = 'startup_failure';

    case Cancelled = 'cancelled';

    case Skipped = 'skipped';

    case Neutral = 'neutral';

    case ActionRequired = 'action_required';

    /** Not finished within GitHub's time limits for checks. */
    case Stale = 'stale';

    /**
     * Read a conclusion from the GitHub API. Runs that haven't finished have none, and so do runs with a
     * conclusion GitHub adds later, which then count as neither passing nor failing.
     */
    public static function fromGitHub(mixed $conclusion): ?self
    {
        return is_string($conclusion) ? self::tryFrom($conclusion) : null;
    }
}
