<?php

namespace App\Models;

use App\Enums\ReviewState;
use App\Models\Concerns\MemoizesImmutableDates;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A cached review on a pull request.
 *
 * @property string $reviewer
 * @property bool $reviewer_is_bot
 * @property ReviewState $state
 * @property CarbonImmutable $submitted_at
 */
#[Unguarded]
#[WithoutTimestamps]
class PullRequestReview extends Model
{
    use MemoizesImmutableDates;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'reviewer_is_bot' => 'boolean',
            'state' => ReviewState::class,
            'submitted_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<PullRequest, $this>
     */
    public function pullRequest(): BelongsTo
    {
        return $this->belongsTo(PullRequest::class);
    }
}
