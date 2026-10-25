<?php

namespace App\Enums;

/**
 * The verdict of a pull request review, as GitHub reports it.
 */
enum ReviewState: string
{
    case Approved = 'APPROVED';

    case ChangesRequested = 'CHANGES_REQUESTED';

    /** Comments without approving or asking for changes. */
    case Commented = 'COMMENTED';

    /** Withdrawn later, so it no longer counts as a verdict. */
    case Dismissed = 'DISMISSED';

    /** Started but not submitted yet. Only its author can see it. */
    case Pending = 'PENDING';
}
