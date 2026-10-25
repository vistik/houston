<?php

namespace App\Models;

use App\Enums\SystemEventLevel;
use App\Enums\SystemEventType;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Something Houston did behind the scenes, such as starting a sync or warming the report cache,
 * kept to help understand and debug what it is doing.
 *
 * @property SystemEventLevel $level
 * @property string $type One of SystemEventType, kept as a string so events of retired types still load.
 * @property string $message
 * @property array<string, mixed>|null $context
 * @property CarbonImmutable $created_at
 */
#[Unguarded]
class SystemEvent extends Model
{
    use MassPrunable;

    /**
     * How long events are kept.
     */
    public const int KeepDays = 30;

    public const ?string UPDATED_AT = null;

    /**
     * @param  array<string, mixed>  $context
     */
    public static function record(SystemEventType $type, string $message, ?Repository $repository = null, array $context = [], SystemEventLevel $level = SystemEventLevel::Info): self
    {
        return static::create([
            'repository_id' => $repository?->id,
            'level' => $level,
            'type' => $type->value,
            'message' => $message,
            'context' => $context === [] ? null : $context,
        ]);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'level' => SystemEventLevel::class,
            'context' => 'array',
            'created_at' => 'immutable_datetime',
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
     * @return Builder<static>
     */
    public function prunable(): Builder
    {
        return static::query()->where('created_at', '<', now()->subDays(self::KeepDays));
    }
}
