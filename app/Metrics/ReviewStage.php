<?php

namespace App\Metrics;

/**
 * Whose turn it is on a pull request that is ready for review; see ReviewFlow.
 */
enum ReviewStage: string
{
    /** Waiting for the first response from a reviewer. */
    case Pickup = 'pickup';

    /** Back with the reviewers after the author responded. */
    case Reviewer = 'reviewer';

    /** With the author after a review. */
    case Author = 'author';

    /** Approved and waiting to be merged. */
    case Merge = 'merge';
}
