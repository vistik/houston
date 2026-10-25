<?php

use App\Enums\PullRequestActivityKind;
use App\Enums\ReviewState;
use App\Metrics\OpenPullRequestStatus;
use App\Metrics\ReviewFlow;
use App\Metrics\ReviewStage;
use App\Metrics\Timeline;
use App\Metrics\WorkingTime;
use App\Models\PullRequest;
use App\Models\PullRequestActivity;
use App\Models\PullRequestReview;
use Carbon\CarbonImmutable;

beforeEach(function () {
    $this->opened = CarbonImmutable::parse('2026-10-05 08:00:00');
    $this->calendar = new WorkingTime('UTC', [1, 2, 3, 4, 5, 6, 7], '00:00', '24:00');
});

/**
 * @param  list<array{0: float, 1: 'review'|PullRequestActivityKind, 2?: string, 3?: ReviewState}>  $timeline  [hours after opening, review or activity, actor, review state]
 */
function pullRequestWithTimeline(CarbonImmutable $opened, array $timeline, ?float $mergedAfter = null): PullRequest
{
    $pr = PullRequest::factory()->lifecycle($opened, mergedAfter: $mergedAfter)->create(['author' => 'alice']);

    foreach ($timeline as $event) {
        [$hours, $kind] = $event;
        $at = $opened->addMinutes((int) round($hours * 60));
        $actor = $event[2] ?? 'bob';

        if ($kind === 'review') {
            PullRequestReview::create(['pull_request_id' => $pr->id, 'reviewer' => $actor, 'state' => $event[3] ?? ReviewState::Commented, 'submitted_at' => $at]);
        } else {
            PullRequestActivity::create(['pull_request_id' => $pr->id, 'kind' => $kind, 'actor' => $actor, 'occurred_at' => $at]);
        }
    }

    return $pr;
}

it('splits a pull request into pickup, author, reviewer and merge time', function () {
    $pr = pullRequestWithTimeline($this->opened, [
        [2, 'review', 'bob', ReviewState::ChangesRequested], // 2h pickup
        [5, PullRequestActivityKind::Commit, 'alice'],                    // 3h with the author
        [6, 'review', 'bob', ReviewState::Approved],          // 1h back with the reviewer
    ], mergedAfter: 10);                           // 4h waiting to merge

    $flow = ReviewFlow::analyse($pr, Timeline::for($pr), $this->calendar, CarbonImmutable::parse('2026-10-07'));

    expect($flow->seconds)->toBe([
        ReviewStage::Pickup->value => 2 * 3600,
        ReviewStage::Reviewer->value => 3600,
        ReviewStage::Author->value => 3 * 3600,
        ReviewStage::Merge->value => 4 * 3600,
    ])
        ->and($flow->rounds)->toBe(1)
        ->and($flow->reworkPushes)->toBe(1);
});

it('counts every time the pull request goes back to the author as a round', function () {
    $pr = pullRequestWithTimeline($this->opened, [
        [1, PullRequestActivityKind::Comment, 'bob'],
        [2, PullRequestActivityKind::ForcePush, 'alice'],
        [3, 'review', 'carol', ReviewState::Commented],
        [4, PullRequestActivityKind::Comment, 'alice'],
        [5, 'review', 'bob', ReviewState::Approved],
    ], mergedAfter: 6);

    $flow = ReviewFlow::analyse($pr, Timeline::for($pr), $this->calendar, CarbonImmutable::parse('2026-10-07'));

    expect($flow->rounds)->toBe(2)
        ->and($flow->reworkPushes)->toBe(1)
        ->and($flow->secondsIn(ReviewStage::Author))->toBe(2 * 3600);
});

it('ignores comments made while merging', function () {
    $pr = pullRequestWithTimeline($this->opened, [[3, PullRequestActivityKind::Comment, 'taylor']], mergedAfter: 3);

    $flow = ReviewFlow::analyse($pr, Timeline::for($pr), $this->calendar, CarbonImmutable::parse('2026-10-07'));

    expect($flow->rounds)->toBe(0)
        ->and($flow->responded)->toBeFalse()
        ->and($flow->secondsIn(ReviewStage::Pickup))->toBe(3 * 3600);
});

it('reports what an open pull request is waiting on', function () {
    $now = $this->opened->addHours(8);

    $waitingOnAuthor = pullRequestWithTimeline($this->opened, [[1, 'review', 'bob', ReviewState::ChangesRequested]]);
    $backWithReviewer = pullRequestWithTimeline($this->opened, [[1, 'review', 'bob', ReviewState::ChangesRequested], [3, PullRequestActivityKind::Commit, 'alice']]);
    $untouched = pullRequestWithTimeline($this->opened, []);
    $approved = pullRequestWithTimeline($this->opened, [[1, 'review', 'bob', ReviewState::Approved]]);

    expect(ReviewFlow::analyse($waitingOnAuthor, Timeline::for($waitingOnAuthor), $this->calendar, $now)->status())->toBe(OpenPullRequestStatus::WaitingOnAuthor)
        ->and(ReviewFlow::analyse($backWithReviewer, Timeline::for($backWithReviewer), $this->calendar, $now)->status())->toBe(OpenPullRequestStatus::WaitingOnReviewer)
        ->and(ReviewFlow::analyse($backWithReviewer, Timeline::for($backWithReviewer), $this->calendar, $now)->stateSince->equalTo($this->opened->addHours(3)))->toBeTrue()
        ->and(ReviewFlow::analyse($untouched, Timeline::for($untouched), $this->calendar, $now)->status())->toBe(OpenPullRequestStatus::AwaitingReview)
        ->and(ReviewFlow::analyse($approved, Timeline::for($approved), $this->calendar, $now)->status())->toBe(OpenPullRequestStatus::Approved);
});

it('skips drafts that were never ready for review', function () {
    $draft = PullRequest::factory()->create(['is_draft' => true, 'ready_at' => null]);

    expect(ReviewFlow::analyse($draft, Timeline::for($draft), $this->calendar, CarbonImmutable::now()))->toBeNull();
});
