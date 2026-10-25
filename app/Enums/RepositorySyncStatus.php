<?php

namespace App\Enums;

/**
 * Where the latest sync of a repository stands.
 */
enum RepositorySyncStatus: string
{
    /** Never synced. */
    case Pending = 'pending';

    /** Waiting for a queue worker, or for a GitHub rate limit to reset. */
    case Queued = 'queued';

    case Running = 'running';

    case Succeeded = 'succeeded';

    case Failed = 'failed';
}
