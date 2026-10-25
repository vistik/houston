<?php

namespace App\Metrics;

use App\Enums\ReviewState;
use App\Models\PullRequest;
use Carbon\CarbonImmutable;

/**
 * Replays a pull request's timeline to work out whose turn it was at every moment.
 *
 * Once ready for review the ball is with the reviewers. A peer review or comment hands it to the
 * author; a push or reply from the author hands it back. An approval moves it to "waiting to merge".
 */
final class ReviewFlow
{
    /**
     * Activity this close to the merge is treated as part of merging ("Thanks!"), not a review round.
     */
    private const int MergeGraceSeconds = 120;

    /**
     * @param  array{pickup: int, reviewer: int, author: int, merge: int}  $seconds
     */
    private function __construct(
        public readonly array $seconds,
        public readonly int $rounds,
        public readonly int $reworkPushes,
        public readonly bool $responded,
        public readonly ReviewStage $state,
        public readonly CarbonImmutable $stateSince,
    ) {}

    /**
     * Returns null for pull requests that never became ready for review.
     */
    public static function analyse(PullRequest $pr, Timeline $timeline, WorkingTime $time, CarbonImmutable $now): ?self
    {
        $start = $pr->ready_at;

        if ($start === null) {
            return null;
        }

        $end = $pr->merged_at ?? $pr->closed_at ?? $now;
        $cutoff = ($pr->merged_at ?? $pr->closed_at)?->subSeconds(self::MergeGraceSeconds) ?? $now;

        $seconds = [ReviewStage::Pickup->value => 0, ReviewStage::Reviewer->value => 0, ReviewStage::Author->value => 0, ReviewStage::Merge->value => 0];
        $state = ReviewStage::Pickup;
        $since = $start;
        $rounds = 0;
        $reworkPushes = 0;
        $responded = false;

        $moveTo = function (ReviewStage $next, CarbonImmutable $at) use (&$state, &$since, &$seconds, $time): void {
            if ($next === $state) {
                return;
            }

            $seconds[$state->value] += $time->between($since, $at);
            $state = $next;
            $since = $at;
        };

        foreach (self::events($pr, $timeline) as [$at, $kind, $verdict]) {
            if ($at->lte($start) || $at->gt($cutoff)) {
                continue;
            }

            if ($kind === ReviewEventKind::Reviewer) {
                $responded = true;

                if ($verdict === ReviewState::Approved) {
                    $moveTo(ReviewStage::Merge, $at);
                } elseif (in_array($state, [ReviewStage::Pickup, ReviewStage::Reviewer], true) || $verdict === ReviewState::ChangesRequested) {
                    $rounds += $state === ReviewStage::Author ? 0 : 1;
                    $moveTo(ReviewStage::Author, $at);
                }

                continue;
            }

            if ($kind === ReviewEventKind::Push && $responded) {
                $reworkPushes++;
            }

            if ($state === ReviewStage::Author) {
                $moveTo(ReviewStage::Reviewer, $at);
            }
        }

        $seconds[$state->value] += $time->between($since, $end);

        return new self(
            [
                ReviewStage::Pickup->value => $seconds[ReviewStage::Pickup->value],
                ReviewStage::Reviewer->value => $seconds[ReviewStage::Reviewer->value],
                ReviewStage::Author->value => $seconds[ReviewStage::Author->value],
                ReviewStage::Merge->value => $seconds[ReviewStage::Merge->value],
            ],
            $rounds,
            $reworkPushes,
            $responded,
            $state,
            $since,
        );
    }

    /**
     * The working time the pull request spent in a stage.
     */
    public function secondsIn(ReviewStage $stage): int
    {
        return $this->seconds[$stage->value];
    }

    /**
     * Status of an open pull request, for the work-in-progress list.
     */
    public function status(): OpenPullRequestStatus
    {
        return match ($this->state) {
            ReviewStage::Pickup => OpenPullRequestStatus::AwaitingReview,
            ReviewStage::Reviewer => OpenPullRequestStatus::WaitingOnReviewer,
            ReviewStage::Author => OpenPullRequestStatus::WaitingOnAuthor,
            ReviewStage::Merge => OpenPullRequestStatus::Approved,
        };
    }

    /**
     * Every timeline event as [moment, who it comes from, review verdict], oldest first.
     *
     * @return list<array{0: CarbonImmutable, 1: ReviewEventKind, 2: ReviewState|null}>
     */
    private static function events(PullRequest $pr, Timeline $timeline): array
    {
        $events = [];

        foreach ($timeline->reviews as $review) {
            if ($review['isBot'] || $review['state'] === ReviewState::Dismissed) {
                continue;
            }

            $events[] = $review['reviewer'] === $pr->author
                ? [$review['at'], ReviewEventKind::Author, null]
                : [$review['at'], ReviewEventKind::Reviewer, $review['state']];
        }

        foreach ($timeline->activities as $activity) {
            if ($activity['isBot']) {
                continue;
            }

            $events[] = match (true) {
                $activity['isPush'] => [$activity['at'], ReviewEventKind::Push, null],
                $activity['actor'] === $pr->author => [$activity['at'], ReviewEventKind::Author, null],
                default => [$activity['at'], ReviewEventKind::Reviewer, ReviewState::Commented],
            };
        }

        usort($events, fn (array $a, array $b): int => $a[0] <=> $b[0]);

        return $events;
    }
}
