<?php

namespace App\Metrics;

/**
 * Who a timeline event comes from, as ReviewFlow replays it to work out whose turn it is.
 */
enum ReviewEventKind
{
    /** A review or comment by someone other than the author. */
    case Reviewer;

    /** A comment or review by the author. */
    case Author;

    /** A commit or force push. */
    case Push;
}
