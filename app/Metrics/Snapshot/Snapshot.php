<?php

namespace App\Metrics\Snapshot;

use App\Models\PullRequest;
use Closure;
use Illuminate\Support\Collection;

/**
 * The repository as it is right now, rather than over a window.
 */
final class Snapshot
{
    /** @var Collection<int, PullRequest>|null */
    private ?Collection $stale = null;

    /**
     * @param  Collection<int, PullRequest>  $open  Every open pull request, drafts included.
     * @param  Closure(): Collection<int, PullRequest>  $makeStale
     */
    public function __construct(
        public readonly Collection $open,
        private Closure $makeStale,
    ) {}

    /**
     * Open pull requests no human has touched (opened, marked ready, reviewed, commented, pushed) for
     * the configured number of days. Bot activity doesn't count.
     *
     * @return Collection<int, PullRequest>
     */
    public function stale(): Collection
    {
        return $this->stale ??= ($this->makeStale)();
    }
}
