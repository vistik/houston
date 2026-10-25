<?php

namespace App\Metrics;

/**
 * Whether the latest CI runs on a branch pass.
 */
enum BranchStatus: string
{
    case Passing = 'passing';

    /** At least one workflow is still red: nothing passed after it broke. */
    case Failing = 'failing';

    /** No runs to tell from. */
    case Unknown = 'unknown';
}
