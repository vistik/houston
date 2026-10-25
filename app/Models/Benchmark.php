<?php

namespace App\Models;

use App\Metrics\Better;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Model;

/**
 * Tier thresholds for one metric, in the metric's own unit. Metrics that count work over the period
 * are benchmarked per week, so the same target holds for any reporting period.
 *
 * @property string $metric
 * @property Better $direction Higher or lower, never neutral.
 * @property float $elite
 * @property float $good
 * @property float $medium
 */
#[Unguarded]
class Benchmark extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'direction' => Better::class,
            'elite' => 'float',
            'good' => 'float',
            'medium' => 'float',
        ];
    }
}
