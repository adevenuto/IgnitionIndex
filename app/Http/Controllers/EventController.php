<?php

namespace App\Http\Controllers;

use App\Actions\Events\DeleteEvent;
use App\Actions\Events\LogEvent;
use App\Actions\Events\UpdateEvent;
use App\Http\Requests\Events\StoreEventRequest;
use App\Http\Requests\Events\UpdateEventRequest;
use App\Models\Event;
use App\Models\Vehicle;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class EventController extends Controller
{
    /**
     * One endpoint for all four quick-add lanes; the request object decides
     * what a given lane contributes and LogEvent keeps the derived state right.
     */
    public function store(StoreEventRequest $request, Vehicle $vehicle, LogEvent $logEvent): RedirectResponse
    {
        Gate::authorize('update', $vehicle);

        $logEvent->handle($vehicle, $request->eventAttributes(), $request->lineItems());

        return back();
    }

    /**
     * Correcting an entry. Everything it fed — odometer, MPG, the gauges a
     * visit reset — is worked out again by UpdateEvent.
     */
    public function update(UpdateEventRequest $request, Event $event, UpdateEvent $updateEvent): RedirectResponse
    {
        Gate::authorize('update', $event->vehicle);

        $updateEvent->handle($event, $request->eventAttributes(), $request->lineItems());

        return back();
    }

    /**
     * Removing a reading must take it out of the estimate too, or the gauges
     * keep counting from a number that is no longer on record.
     */
    public function destroy(Event $event, DeleteEvent $deleteEvent): RedirectResponse
    {
        Gate::authorize('update', $event->vehicle);

        $deleteEvent->handle($event);

        return back();
    }
}
