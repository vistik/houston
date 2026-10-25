<?php

namespace App\Models;

use App\Enums\ChecksState;
use App\Enums\PullRequestState;
use App\Models\Concerns\MemoizesImmutableDates;
use Carbon\CarbonImmutable;
use Database\Factories\PullRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * A cached GitHub pull request, including the derived lifecycle timestamps used for velocity metrics.
 *
 * @property PullRequestState $state
 * @property ChecksState|null $checks_state Null when the latest commit has no checks.
 * @property array<int, array{name: string, color: string}>|null $labels
 * @property CarbonImmutable $opened_at
 * @property CarbonImmutable|null $first_commit_at
 * @property CarbonImmutable|null $ready_at
 * @property list<array{from: int, until: int|null}>|null $draft_intervals Unix timestamps of when it was a draft, or null when not fetched since this was recorded.
 * @property CarbonImmutable|null $first_response_at
 * @property CarbonImmutable|null $first_review_at
 * @property CarbonImmutable|null $first_approval_at
 * @property CarbonImmutable|null $merged_at
 * @property CarbonImmutable|null $closed_at
 * @property CarbonImmutable|null $last_activity_at
 */
#[Unguarded]
class PullRequest extends Model
{
    /** @use HasFactory<PullRequestFactory> */
    use HasFactory;

    use MemoizesImmutableDates;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'state' => PullRequestState::class,
            'checks_state' => ChecksState::class,
            'is_draft' => 'boolean',
            'author_is_bot' => 'boolean',
            'labels' => 'array',
            'first_commit_at' => 'immutable_datetime',
            'opened_at' => 'immutable_datetime',
            'ready_at' => 'immutable_datetime',
            'draft_intervals' => 'array',
            'first_response_at' => 'immutable_datetime',
            'first_review_at' => 'immutable_datetime',
            'first_approval_at' => 'immutable_datetime',
            'merged_at' => 'immutable_datetime',
            'closed_at' => 'immutable_datetime',
            'last_activity_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<Repository, $this>
     */
    public function repository(): BelongsTo
    {
        return $this->belongsTo(Repository::class);
    }

    /**
     * @return HasMany<PullRequestReview, $this>
     */
    public function reviews(): HasMany
    {
        return $this->hasMany(PullRequestReview::class);
    }

    /**
     * @return HasMany<PullRequestActivity, $this>
     */
    public function activities(): HasMany
    {
        return $this->hasMany(PullRequestActivity::class);
    }

    /**
     * The avatar of each of the given authors, by login in lower case, for those with a cached pull request.
     *
     * @param  array<int, string>  $logins
     * @return array<string, string>
     */
    public static function avatarUrls(array $logins): array
    {
        return self::query()
            ->whereIn(DB::raw('lower(author)'), array_map(Str::lower(...), $logins))
            ->whereNotNull('author_avatar_url')
            ->toBase()
            ->selectRaw('lower(author) as login, max(author_avatar_url) as avatar_url')
            ->groupByRaw('lower(author)')
            ->pluck('avatar_url', 'login')
            ->all();
    }

    /**
     * Whether this pull request fixes a bad change: a revert, hotfix or rollback.
     */
    public function isRemediation(): bool
    {
        $pattern = config()->string('houston.remediation_pattern');

        return preg_match($pattern, $this->title) === 1
            || preg_match($pattern, (string) $this->head_ref) === 1
            || collect($this->labels ?? [])->contains(fn (array $label): bool => preg_match($pattern, $label['name']) === 1);
    }

    /**
     * The moment the pull request was handed over to reviewers.
     */
    public function readyForReviewAt(): CarbonImmutable
    {
        return $this->ready_at ?? $this->opened_at;
    }

    public function size(): int
    {
        return $this->additions + $this->deletions;
    }
}
