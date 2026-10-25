<?php

namespace App\Http\Controllers\Concerns;

use App\Models\TimelineEvent;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

trait PresentsTimelineEvents
{
    /**
     * The event the request asks to compare before and after, if it still exists.
     */
    private function focusedEvent(Request $request): ?TimelineEvent
    {
        return $request->filled('event') ? TimelineEvent::query()->find($request->integer('event')) : null;
    }

    /**
     * The focused event, with where the synced history starts so the page can tell when it doesn't
     * reach back far enough to cover the stretch before the event.
     *
     * @return array{id: int, title: string, date: string, historyFrom: string}|null
     */
    private function focusedEventPayload(?TimelineEvent $event, CarbonImmutable $historyFrom): ?array
    {
        return $event === null ? null : [...$event->toPayload(), 'historyFrom' => $historyFrom->toDateString()];
    }
}
