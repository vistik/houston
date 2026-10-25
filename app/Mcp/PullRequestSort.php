<?php

namespace App\Mcp;

/**
 * How the list-pull-requests tool orders pull requests.
 */
enum PullRequestSort: string
{
    /** By last state change, newest first. */
    case Recent = 'recent';

    /** By opening date, oldest first. */
    case Oldest = 'oldest';

    /** By changed lines, most first. */
    case Largest = 'largest';

    /** By time from opening to merge, close or now, longest first. */
    case Slowest = 'slowest';
}
