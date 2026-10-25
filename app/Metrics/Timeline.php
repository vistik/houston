<?php

namespace App\Metrics;

use App\Enums\PullRequestActivityKind;
use App\Enums\ReviewState;
use App\Models\PullRequest;
use App\Models\PullRequestActivity;
use App\Models\PullRequestReview;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * The reviews and activities of one pull request, as plain values.
 *
 * Reports replay tens of thousands of these, so they are read as rows rather than Eloquent models,
 * which are an order of magnitude slower to build and to read.
 *
 * @phpstan-type Review array{reviewer: string, isBot: bool, state: ReviewState, comments: int, at: CarbonImmutable}
 * @phpstan-type Activity array{actor: string|null, isBot: bool, isPush: bool, at: CarbonImmutable}
 */
final readonly class Timeline
{
    /**
     * @param  list<Review>  $reviews
     * @param  list<Activity>  $activities
     */
    public function __construct(public array $reviews = [], public array $activities = []) {}

    /**
     * The timelines of many pull requests in two queries, keyed by pull request id. Pull requests
     * without any reviews or activities get an empty timeline.
     *
     * @param  list<int>  $pullRequestIds
     * @return array<int, self>
     */
    public static function load(array $pullRequestIds): array
    {
        if ($pullRequestIds === []) {
            return [];
        }

        $reviews = array_fill_keys($pullRequestIds, []);
        $activities = array_fill_keys($pullRequestIds, []);

        $reviewRows = PullRequestReview::query()
            ->whereIntegerInRaw('pull_request_id', $pullRequestIds)
            ->orderBy('id')
            ->toBase()
            ->get(['pull_request_id', 'reviewer', 'reviewer_is_bot', 'state', 'comments_count', 'submitted_at']);

        foreach ($reviewRows as $row) {
            $reviews[$row->pull_request_id][] = [
                'reviewer' => $row->reviewer,
                'isBot' => (bool) $row->reviewer_is_bot,
                'state' => ReviewState::from($row->state),
                'comments' => (int) $row->comments_count,
                'at' => self::date($row->submitted_at),
            ];
        }

        $activityRows = PullRequestActivity::query()
            ->whereIntegerInRaw('pull_request_id', $pullRequestIds)
            ->orderBy('id')
            ->toBase()
            ->get(['pull_request_id', 'kind', 'actor', 'actor_is_bot', 'occurred_at']);

        foreach ($activityRows as $row) {
            $activities[$row->pull_request_id][] = [
                'actor' => $row->actor,
                'isBot' => (bool) $row->actor_is_bot,
                'isPush' => PullRequestActivityKind::from($row->kind)->isPush(),
                'at' => self::date($row->occurred_at),
            ];
        }

        return array_map(
            fn (int $id): self => new self($reviews[$id], $activities[$id]),
            array_combine($pullRequestIds, $pullRequestIds),
        );
    }

    public static function for(PullRequest $pr): self
    {
        return self::load([$pr->id])[$pr->id];
    }

    /**
     * Parse a timestamp column the way Eloquent's date casts do.
     */
    private static function date(string $value): CarbonImmutable
    {
        try {
            return CarbonImmutable::createFromFormat('Y-m-d H:i:s', $value);
        } catch (InvalidArgumentException) {
            return CarbonImmutable::parse($value);
        }
    }
}
