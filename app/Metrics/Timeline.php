<?php

namespace App\Metrics;

use App\Enums\PullRequestActivityKind;
use App\Enums\ReviewState;
use App\Models\PullRequest;
use App\Models\PullRequestActivity;
use App\Models\PullRequestReview;
use Carbon\CarbonImmutable;
use DateTimeImmutable;
use DateTimeZone;

/**
 * The reviews and activities of one pull request, as plain values.
 *
 * Reports replay tens of thousands of these, so they are read as rows rather than Eloquent models,
 * which are an order of magnitude slower to build and to read. Moments are kept as unix timestamps:
 * a busy repository has millions of them, and a date object for each runs a worker out of memory.
 * Turn one into a date with moment() where it is needed. Activities are what reports have the most
 * of, so they are kept as two lists of plain values rather than a row each, and those by bots, which
 * no report reads, are left out.
 *
 * @phpstan-type Review array{reviewer: string, isBot: bool, state: ReviewState, comments: int, at: int}
 */
final readonly class Timeline
{
    /**
     * @param  list<Review>  $reviews
     * @param  list<int>  $activityTimes  When each commit, force push and comment by a person happened.
     * @param  list<ReviewEventKind>  $activityKinds  Who each of those came from, in the same order.
     */
    public function __construct(public array $reviews = [], public array $activityTimes = [], public array $activityKinds = []) {}

    /**
     * The timelines of many pull requests in two queries, keyed by pull request id. Pull requests
     * without any reviews or activities get an empty timeline.
     *
     * @param  iterable<PullRequest>  $pullRequests
     * @return array<int, self>
     */
    public static function load(iterable $pullRequests): array
    {
        $authors = [];

        foreach ($pullRequests as $pr) {
            $authors[(int) $pr->id] = $pr->author;
        }

        if ($authors === []) {
            return [];
        }

        $ids = array_keys($authors);
        $reviews = array_fill_keys($ids, []);
        $activityTimes = array_fill_keys($ids, []);
        $activityKinds = array_fill_keys($ids, []);

        $reviewRows = PullRequestReview::query()
            ->whereIntegerInRaw('pull_request_id', $ids)
            ->orderBy('id')
            ->toBase()
            ->select(['pull_request_id', 'reviewer', 'reviewer_is_bot', 'state', 'comments_count', 'submitted_at'])
            ->cursor();

        foreach ($reviewRows as $row) {
            $reviews[$row->pull_request_id][] = [
                'reviewer' => $row->reviewer,
                'isBot' => (bool) $row->reviewer_is_bot,
                'state' => ReviewState::from($row->state),
                'comments' => (int) $row->comments_count,
                'at' => self::timestamp($row->submitted_at),
            ];
        }

        $activityRows = PullRequestActivity::query()
            ->whereIntegerInRaw('pull_request_id', $ids)
            ->where('actor_is_bot', false)
            ->orderBy('id')
            ->toBase()
            ->select(['pull_request_id', 'kind', 'actor', 'occurred_at'])
            ->cursor();

        foreach ($activityRows as $row) {
            $activityTimes[$row->pull_request_id][] = self::timestamp($row->occurred_at);
            $activityKinds[$row->pull_request_id][] = match (true) {
                PullRequestActivityKind::from($row->kind)->isPush() => ReviewEventKind::Push,
                $row->actor === $authors[$row->pull_request_id] => ReviewEventKind::Author,
                default => ReviewEventKind::Reviewer,
            };
        }

        return array_map(
            fn (int $id): self => new self($reviews[$id], $activityTimes[$id], $activityKinds[$id]),
            array_combine($ids, $ids),
        );
    }

    public static function for(PullRequest $pr): self
    {
        return self::load([$pr])[$pr->id];
    }

    /**
     * A moment of a review or activity as a date, as Eloquent's date casts would have read it. Going
     * through a plain date is almost twice as fast as Carbon's createFromTimestamp().
     */
    public static function moment(int $timestamp): CarbonImmutable
    {
        static $zones = [];

        $zone = date_default_timezone_get();

        return CarbonImmutable::instance((new DateTimeImmutable("@{$timestamp}"))->setTimezone($zones[$zone] ??= new DateTimeZone($zone)));
    }

    /**
     * Parse a timestamp column the way Eloquent's date casts do, into a unix timestamp.
     */
    private static function timestamp(string $value): int
    {
        return strtotime($value) ?: CarbonImmutable::parse($value)->getTimestamp();
    }
}
