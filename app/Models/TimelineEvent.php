<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\TimelineEventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Something that changed how people work, such as introducing AI reviews or a Slack bot that nudges
 * reviewers. It is marked on every timeline, and opening it compares the days since with the equally
 * long stretch before.
 *
 * @property string $title
 * @property CarbonImmutable $happened_on
 */
#[Fillable(['title', 'happened_on'])]
class TimelineEvent extends Model
{
    /** @use HasFactory<TimelineEventFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'happened_on' => 'immutable_date',
        ];
    }

    /**
     * @return array{id: int, title: string, date: string}
     */
    public function toPayload(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'date' => $this->happened_on->toDateString(),
        ];
    }

    /**
     * Every event, oldest first.
     *
     * @return list<array{id: int, title: string, date: string}>
     */
    public static function payloads(): array
    {
        return array_values(self::query()->orderBy('happened_on')->orderBy('id')->get()
            ->map(fn (self $event): array => $event->toPayload())
            ->all());
    }
}
