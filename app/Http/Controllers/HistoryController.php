<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\LineItem;
use App\Models\ServiceType;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HistoryController extends Controller
{
    /**
     * Every event the user has logged, newest first, across all their vehicles.
     * Each row carries what the edit sheet needs to prefill it.
     */
    public function __invoke(Request $request): Response
    {
        $events = Event::query()
            ->whereHas('vehicle', fn ($query) => $query->where('user_id', $request->user()->id))
            ->with(['vehicle', 'lineItems.serviceType'])
            ->latestFirst()
            ->paginate(30)
            ->through(fn (Event $event): array => [
                'id' => $event->id,
                'vehicle_id' => $event->vehicle_id,
                'vehicle_name' => $event->vehicle->displayName(),
                'type' => $event->type->value,
                'type_label' => $event->type->label(),
                'odometer' => $event->odometer,
                'occurred_on' => $event->occurred_on->toDateString(),
                'cost_cents' => $event->cost_cents,
                'notes' => $event->notes,
                'location' => $event->location,
                'gallons' => $event->gallons === null ? null : (float) $event->gallons,
                'full_tank' => $event->full_tank,
                'mpg' => $event->mpg === null ? null : (float) $event->mpg,
                'category' => $event->category,
                'line_items' => $event->lineItems->map(fn (LineItem $item): array => [
                    'id' => $item->id,
                    'service_type_id' => $item->service_type_id,
                    'name' => $item->serviceType->name,
                    'cost_cents' => $item->cost_cents,
                ])->all(),
            ]);

        // Opening an entry edits it in the same sheet it was logged with, which
        // needs the same option lists.
        return Inertia::render('History', [
            'events' => $events,
            'readings' => $this->readings($request),
            'serviceTypes' => ServiceType::orderBy('sort_order')->get(['id', 'name', 'category']),
            'expenseCategories' => config('vehicles.expense_categories'),
        ]);
    }

    /**
     * Every reading per vehicle, oldest first, across all pages.
     *
     * The edit sheet works out from these, as the date field changes, which
     * readings bound the one being edited, whether it is the vehicle's first,
     * and whether it is the only one. A page of 30 entries cannot answer that:
     * the neighbours of the last row on a page are on the next one. The server
     * still validates; this only lets the sheet say so before saving.
     *
     * @return array<int, list<array{id: int, odometer: int, occurred_on: string}>>
     */
    private function readings(Request $request): array
    {
        return Event::query()
            ->whereHas('vehicle', fn ($query) => $query->where('user_id', $request->user()->id))
            ->orderBy('occurred_on')
            ->orderBy('odometer')
            ->orderBy('id')
            ->get(['id', 'vehicle_id', 'odometer', 'occurred_on'])
            ->groupBy('vehicle_id')
            ->map(fn ($events) => $events->map(fn (Event $event): array => [
                'id' => $event->id,
                'odometer' => $event->odometer,
                'occurred_on' => $event->occurred_on->toDateString(),
            ])->values()->all())
            ->all();
    }
}
