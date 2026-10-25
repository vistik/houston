<?php

namespace App\GitHub;

/**
 * How far along one phase of a sync is; see SyncProgress.
 */
enum SyncPhaseState: string
{
    case Pending = 'pending';

    case Active = 'active';

    case Done = 'done';

    case Failed = 'failed';

    /** Not needed for this sync. */
    case Skipped = 'skipped';
}
