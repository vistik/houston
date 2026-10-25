<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * How far behind a repository's direct dependencies were on a day.
 *
 * @property CarbonImmutable $recorded_on
 * @property int $total
 * @property int $outdated
 * @property int $major
 * @property CarbonImmutable $checked_at
 */
#[Unguarded]
#[WithoutTimestamps]
class DependencySnapshot extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'recorded_on' => 'immutable_date',
            'total' => 'integer',
            'outdated' => 'integer',
            'major' => 'integer',
            'checked_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<Repository, $this>
     */
    public function repository(): BelongsTo
    {
        return $this->belongsTo(Repository::class);
    }
}
