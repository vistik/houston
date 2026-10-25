<?php

namespace App\Models;

use App\Enums\PullRequestActivityKind;
use App\Models\Concerns\MemoizesImmutableDates;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A cached timeline event on a pull request that is not a review.
 *
 * @property PullRequestActivityKind $kind
 * @property string|null $actor
 * @property bool $actor_is_bot
 * @property CarbonImmutable $occurred_at
 */
#[Unguarded]
#[WithoutTimestamps]
class PullRequestActivity extends Model
{
    use MemoizesImmutableDates;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => PullRequestActivityKind::class,
            'actor_is_bot' => 'boolean',
            'occurred_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<PullRequest, $this>
     */
    public function pullRequest(): BelongsTo
    {
        return $this->belongsTo(PullRequest::class);
    }

    /**
     * Whether this activity changed the code under review.
     */
    public function isPush(): bool
    {
        return $this->kind->isPush();
    }
}
