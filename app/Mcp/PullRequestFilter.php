<?php

namespace App\Mcp;

/**
 * Which pull requests the list-pull-requests tool lists.
 */
enum PullRequestFilter: string
{
    /** Every open pull request, regardless of age. */
    case Open = 'open';

    /** Those merged within the window. */
    case Merged = 'merged';

    /** Those closed without merging within the window. */
    case Closed = 'closed';

    /** Open ones, together with any opened or closed within the window. */
    case All = 'all';
}
