<?php

namespace App\Enums;

/**
 * Where a pull request stands, as GitHub reports it.
 */
enum PullRequestState: string
{
    case Open = 'OPEN';

    case Merged = 'MERGED';

    /** Closed without being merged. */
    case Closed = 'CLOSED';
}
