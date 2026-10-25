<?php

namespace App\Enums;

/**
 * Something that happened on a pull request besides a review.
 */
enum PullRequestActivityKind: string
{
    case Commit = 'commit';

    case ForcePush = 'force_push';

    /** A comment on the conversation, outside of a review. */
    case Comment = 'comment';

    /**
     * Whether the activity changed the code, as opposed to talking about it.
     */
    public function isPush(): bool
    {
        return $this === self::Commit || $this === self::ForcePush;
    }
}
