<?php

namespace App\Http\Controllers\Settings;

use App\Enums\SystemEventLevel;
use App\Enums\SystemEventType;
use App\Http\Controllers\Controller;
use App\Http\Requests\TimelineEventRequest;
use App\Models\SystemEvent;
use App\Models\TimelineEvent;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class TimelineEventController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('settings/events', [
            'events' => array_reverse(TimelineEvent::payloads()),
        ]);
    }

    public function store(TimelineEventRequest $request): RedirectResponse
    {
        $event = TimelineEvent::create($request->event());

        SystemEvent::record(SystemEventType::TimelineEventAdded, "Marked “{$event->title}” on the timelines on {$event->happened_on->toFormattedDateString()}");

        return to_route('settings.events.index');
    }

    public function update(TimelineEventRequest $request, TimelineEvent $event): RedirectResponse
    {
        $event->update($request->event());

        SystemEvent::record(SystemEventType::TimelineEventUpdated, "Changed the timeline event “{$event->title}” on {$event->happened_on->toFormattedDateString()}");

        return to_route('settings.events.index');
    }

    public function destroy(TimelineEvent $event): RedirectResponse
    {
        $event->delete();

        SystemEvent::record(SystemEventType::TimelineEventRemoved, "Removed the timeline event “{$event->title}”", level: SystemEventLevel::Warning);

        return to_route('settings.events.index');
    }
}
