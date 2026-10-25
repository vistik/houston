<?php

namespace App\Metrics\PullRequests;

use App\Metrics\ReviewFlow;
use App\Metrics\Timeline;
use App\Models\PullRequest;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Support\Collection;

/**
 * The pull requests that moved within a window, by what happened to them.
 */
final class PullRequestWindow
{
    /** @var Collection<int, string>|null */
    private ?Collection $reviewers = null;

    /** @var Collection<int, ReviewFlow>|null */
    private ?Collection $flows = null;

    /**
     * @param  Collection<int, PullRequest>  $merged  Merged within the window.
     * @param  Collection<int, PullRequest>  $opened  Opened within the window.
     * @param  Collection<int, PullRequest>  $abandoned  Closed without merging within the window.
     * @param  Collection<int, PullRequest>  $pickedUp  First responded to within the window.
     * @param  Collection<int, PullRequest>  $approved  First approved within the window.
     * @param  int  $days  How long the window is.
     * @param  array<int, string|null>  $defaultBranches  The default branch of each repository, by repository id.
     * @param  Closure(): Collection<int, string>  $makeReviewers
     * @param  Closure(?CarbonImmutable, ?CarbonImmutable): int  $seconds
     * @param  Closure(Collection<int, PullRequest>): mixed  $loadTimelines
     * @param  Closure(PullRequest): Timeline  $timeline
     * @param  Closure(PullRequest): ?ReviewFlow  $flow
     */
    public function __construct(
        public readonly Collection $merged,
        public readonly Collection $opened,
        public readonly Collection $abandoned,
        public readonly Collection $pickedUp,
        public readonly Collection $approved,
        public readonly int $days,
        public readonly array $defaultBranches,
        private Closure $makeReviewers,
        private Closure $seconds,
        private Closure $loadTimelines,
        private Closure $timeline,
        private Closure $flow,
    ) {}

    /**
     * The reviewer of every peer review within the window: reviews by someone other than the author, and not by a bot.
     *
     * @return Collection<int, string>
     */
    public function reviewers(): Collection
    {
        return $this->reviewers ??= ($this->makeReviewers)();
    }

    /**
     * Working time between two moments, or zero when either is missing.
     */
    public function seconds(?CarbonImmutable $from, ?CarbonImmutable $to): int
    {
        return ($this->seconds)($from, $to);
    }

    /**
     * The reviews and activities of a pull request. Load them for many pull requests at once with withTimelines() first.
     */
    public function timeline(PullRequest $pr): Timeline
    {
        return ($this->timeline)($pr);
    }

    /**
     * Load the timelines of the given pull requests in two queries, so timeline() can read them.
     *
     * @template TCollection of Collection<int, PullRequest>
     *
     * @param  TCollection  $pullRequests
     * @return TCollection
     */
    public function withTimelines(Collection $pullRequests): Collection
    {
        ($this->loadTimelines)($pullRequests);

        return $pullRequests;
    }

    /**
     * Whose turn it was while each merged pull request was in review, for those that were ever ready for review.
     *
     * @return Collection<int, ReviewFlow>
     */
    public function flows(): Collection
    {
        return $this->flows ??= $this->withTimelines($this->merged)
            ->map(fn (PullRequest $pr): ?ReviewFlow => ($this->flow)($pr))
            ->filter()
            ->values();
    }

    /**
     * Whether the pull request was merged into the default branch of its repository.
     */
    public function intoDefaultBranch(PullRequest $pr): bool
    {
        return $pr->base_ref !== null && $pr->base_ref === ($this->defaultBranches[$pr->repository_id] ?? null);
    }
}
