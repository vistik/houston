<?php

namespace App\Models;

use App\Enums\DeploymentSource;
use App\Enums\DeploymentState;
use App\Models\Concerns\MemoizesImmutableDates;
use Carbon\CarbonImmutable;
use Database\Factories\DeploymentFactory;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A cached production deployment, from GitHub Deployments or a GitHub release.
 *
 * @property DeploymentSource $source
 * @property DeploymentState $state
 * @property string|null $ref
 * @property CarbonImmutable|null $committed_at When the deployed commit was made.
 * @property CarbonImmutable $deployed_at
 */
#[Unguarded]
#[WithoutTimestamps]
class Deployment extends Model
{
    /** @use HasFactory<DeploymentFactory> */
    use HasFactory;

    use MemoizesImmutableDates;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'source' => DeploymentSource::class,
            'state' => DeploymentState::class,
            'committed_at' => 'immutable_datetime',
            'deployed_at' => 'immutable_datetime',
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
     * The latest merge this deployment can contain: its commit, or when it went live if the commit is unknown.
     */
    public function cutOffAt(): CarbonImmutable
    {
        return $this->committed_at ?? $this->deployed_at;
    }

    public function succeeded(): bool
    {
        return $this->state === DeploymentState::Success;
    }

    public function failed(): bool
    {
        return $this->state->failed();
    }
}
