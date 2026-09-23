<?php

namespace App\Http\Controllers;

use App\Actions\Events\LogEvent;
use App\Actions\Vehicles\SeedVehicleIntervals;
use App\Actions\Vehicles\SyncVehiclePhotos;
use App\Enums\EventType;
use App\Enums\GaugeStatus;
use App\Http\Requests\Vehicles\StoreVehicleRequest;
use App\Http\Requests\Vehicles\UpdateVehicleRequest;
use App\Jobs\DecodeVehicleVin;
use App\Jobs\FetchEpaFuelEconomy;
use App\Models\Recall;
use App\Models\ServiceType;
use App\Models\Vehicle;
use App\Models\VehiclePhoto;
use App\Notifications\SetUpGaugesNotification;
use App\Support\IntervalProgress;
use App\Support\VehicleGauges;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class VehicleController extends Controller
{
    /**
     * The garage: one card per vehicle. Phase 2 adds the health ring.
     */
    public function index(Request $request): Response
    {
        $vehicles = $request->user()->vehicles()
            ->with(['primaryPhoto', 'intervals.serviceType'])
            ->withCount(['recalls' => fn ($q) => $q->whereNull('acknowledged_at')])
            ->withCount('events')
            ->orderBy('created_at')
            ->get()
            ->map(function (Vehicle $vehicle): array {
                $gauges = VehicleGauges::for($vehicle);
                $worst = $gauges->worst();

                return [
                    'id' => $vehicle->id,
                    'name' => $vehicle->displayName(),
                    'year' => $vehicle->year,
                    'make' => $vehicle->make,
                    'model' => $vehicle->model,
                    'trim' => $vehicle->trim,
                    'color' => $vehicle->color,
                    'photo_thumb_url' => $vehicle->primaryPhoto
                        ? route('vehicle-photos.thumbnail', $vehicle->primaryPhoto)
                        : null,
                    'photo_color' => $vehicle->primaryPhoto?->placeholder_color,
                    'last_odometer' => $vehicle->last_odometer,
                    'last_odometer_at' => $vehicle->last_odometer_at?->toDateString(),
                    'events_count' => $vehicle->events_count,
                    // One ring per card: whatever is closest to due, named. An
                    // averaged score would let nine healthy items hide one overdue
                    // brake job.
                    'worst' => $worst === null ? null : VehicleGauges::gaugeToArray($worst),
                    'due_count' => $gauges->needingAttention()->count(),
                    'open_recall_count' => $vehicle->recalls_count,
                    'uncalibrated_count' => $gauges->uncalibratedCount(),
                    'awaiting_setup' => $gauges->awaitingSetup(),
                    'mileage' => $gauges->mileageToArray(),
                ];
            });

        return Inertia::render('Garage', [
            'vehicles' => $vehicles,
            // The garage FAB opens the quick-add sheet in place, so the sheet's
            // options travel with the page rather than costing a second request.
            'serviceTypes' => ServiceType::orderBy('sort_order')->get(['id', 'name', 'category']),
            'expenseCategories' => config('vehicles.expense_categories'),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('vehicles/Create', [
            'makes' => config('vehicles.makes'),
            'colors' => config('vehicles.colors'),
            'minYear' => config('vehicles.min_year'),
        ]);
    }

    /**
     * Creating a vehicle also writes its first event. The odometer the user
     * gives at setup is a reading like any other, so it enters through the
     * spine rather than being set directly on the vehicle.
     */
    public function store(
        StoreVehicleRequest $request,
        LogEvent $logEvent,
        SyncVehiclePhotos $syncPhotos,
        SeedVehicleIntervals $seedIntervals,
    ): RedirectResponse {
        $data = $request->validated();

        $vehicle = $request->user()->vehicles()->create(
            collect($data)->except([
                'odometer', 'photos', 'photo_order', 'removed_photo_ids',
            ])->all(),
        );

        $seedIntervals->handle($vehicle);

        // Enrichment, never a dependency: the car is already saved and usable,
        // and a vPIC outage simply means specs stay as typed.
        if (filled($vehicle->vin)) {
            DecodeVehicleVin::dispatch($vehicle);
        } else {
            // No VIN to decode, but year/make/model alone are enough for the
            // EPA sticker lookup.
            FetchEpaFuelEconomy::dispatch($vehicle);
        }

        $syncPhotos->handle($vehicle, $request->photos(), $request->photoOrder());

        $logEvent->handle($vehicle, [
            'type' => EventType::Odometer,
            'odometer' => $data['odometer'],
            'occurred_on' => now()->toDateString(),
            'notes' => 'Starting reading',
        ]);

        // Every car, not just the first. This was once first-only, to keep the
        // inbox quiet — but every new vehicle shows the "your gauges aren't set"
        // banner on its own page, so firing for some and not others left the two
        // contradicting each other. A notice belongs to a vehicle, and each
        // vehicle earns its own.
        $request->user()->notify(new SetUpGaugesNotification($vehicle));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Vehicle added.')]);

        // Straight to the car itself, with its whole gauge wall showing. The old
        // quick-calibrate detour asked four questions before the owner had seen
        // what they were answering about; a gauge is calibrated by tapping it.
        return to_route('vehicles.show', $vehicle);
    }

    public function show(Request $request, Vehicle $vehicle): Response
    {
        Gate::authorize('view', $vehicle);

        $vehicle->load(['primaryPhoto', 'intervals.serviceType', 'recalls']);

        $gauges = VehicleGauges::for($vehicle);

        return Inertia::render('vehicles/Show', [
            // The switcher row: every vehicle in the garage, in a stable order,
            // so the active chip does not move when a gauge changes.
            'vehicles' => $request->user()->vehicles()
                ->orderBy('created_at')
                ->get()
                ->map(fn (Vehicle $other): array => [
                    'id' => $other->id,
                    'name' => $other->displayName(),
                    'color' => $other->color,
                    'spec' => trim(implode(' ', array_filter([
                        $other->year,
                        $other->make,
                        $other->model,
                        $other->trim,
                    ]))),
                    'is_active' => $other->id === $vehicle->id,
                ])->values(),
            'vehicle' => [
                'id' => $vehicle->id,
                'name' => $vehicle->displayName(),
                'nickname' => $vehicle->nickname,
                'vin' => $vehicle->vin,
                'year' => $vehicle->year,
                'make' => $vehicle->make,
                'model' => $vehicle->model,
                'trim' => $vehicle->trim,
                'engine' => $vehicle->engine,
                'color' => $vehicle->color,
                'photo_url' => $vehicle->primaryPhoto
                    ? route('vehicle-photos.show', $vehicle->primaryPhoto)
                    : null,
                'photo_color' => $vehicle->primaryPhoto?->placeholder_color,
                'last_odometer' => $vehicle->last_odometer,
                'last_odometer_at' => $vehicle->last_odometer_at?->toDateString(),
                // Rounded to the month for the header's metric row; null when
                // there is no rate yet rather than a misleading zero.
                'avg_miles_per_month' => $gauges->mileage->milesPerDay === null
                    ? null
                    : (int) round($gauges->mileage->milesPerDay * IntervalProgress::DAYS_PER_MONTH),
                'awaiting_setup' => $gauges->awaitingSetup(),
                'services_due_count' => $gauges->needingAttention()->count(),
                'services_overdue_count' => $gauges->gauges
                    ->filter(fn (IntervalProgress $g): bool => $g->status === GaugeStatus::Overdue)
                    ->count(),
            ],
            'recalls' => $vehicle->recalls
                ->filter(fn (Recall $recall): bool => $recall->isOpen())
                ->map(fn (Recall $recall): array => [
                    'id' => $recall->id,
                    'campaign_number' => $recall->campaign_number,
                    'component' => $recall->component,
                    'summary' => $recall->summary,
                    'remedy' => $recall->remedy,
                    'reported_on' => $recall->reported_on?->toDateString(),
                ])->values(),
            'gauges' => $gauges->toArray(),
            'fuel' => VehicleGauges::fuelBenchmark($vehicle),
            'mileage' => $gauges->mileageToArray(),
            'serviceTypes' => ServiceType::orderBy('sort_order')->get(['id', 'name', 'category']),
            'expenseCategories' => config('vehicles.expense_categories'),
        ]);
    }

    public function edit(Vehicle $vehicle): Response
    {
        Gate::authorize('update', $vehicle);

        return Inertia::render('vehicles/Edit', [
            'vehicle' => [
                ...$vehicle->only([
                    'id', 'nickname', 'vin', 'year', 'make', 'model', 'trim', 'engine', 'color',
                ]),
                // What to call this car in prose, on the one screen that also
                // carries the fields it is derived from. Sent rather than
                // reassembled client-side so the breadcrumb, the remove dialog
                // and every other screen name it the same way.
                'name' => $vehicle->displayName(),
                'photos' => $vehicle->photos()->get()->map(fn (VehiclePhoto $photo): array => [
                    'id' => $photo->id,
                    'url' => route('vehicle-photos.thumbnail', $photo),
                    'color' => $photo->placeholder_color,
                ])->all(),
            ],
            'makes' => config('vehicles.makes'),
            'colors' => config('vehicles.colors'),
            'minYear' => config('vehicles.min_year'),
        ]);
    }

    public function update(
        UpdateVehicleRequest $request,
        Vehicle $vehicle,
        SyncVehiclePhotos $syncPhotos,
    ): RedirectResponse {
        Gate::authorize('update', $vehicle);

        $vehicle->update(
            collect($request->validated())->except([
                'photos', 'photo_order', 'removed_photo_ids',
            ])->all(),
        );

        if ($vehicle->wasChanged('vin') && filled($vehicle->vin)) {
            DecodeVehicleVin::dispatch($vehicle);
        }

        $syncPhotos->handle(
            $vehicle,
            $request->photos(),
            $request->photoOrder(),
            $request->removedPhotoIds(),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Vehicle updated.')]);

        return to_route('vehicles.show', $vehicle);
    }

    public function destroy(Vehicle $vehicle): RedirectResponse
    {
        Gate::authorize('delete', $vehicle);

        $vehicle->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Vehicle removed.')]);

        return to_route('garage');
    }
}
