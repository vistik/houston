<?php

namespace App\Metrics;

/**
 * What an open pull request is waiting on, for the work-in-progress list.
 */
enum OpenPullRequestStatus: string
{
    /** Ready for review, and no reviewer has responded yet. */
    case AwaitingReview = 'awaiting_review';

    /** Back with the reviewers after the author responded. */
    case WaitingOnReviewer = 'waiting_on_reviewer';

    case WaitingOnAuthor = 'waiting_on_author';

    /** Approved and waiting to be merged. */
    case Approved = 'approved';

    case Draft = 'draft';

    /**
     * Whether a reviewer can pick the pull request up.
     */
    public function needsReview(): bool
    {
        return $this === self::AwaitingReview || $this === self::WaitingOnReviewer;
    }
}
