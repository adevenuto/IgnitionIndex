<?php

namespace App\Http\Requests\Events;

use App\Enums\EventType;
use App\Models\Vehicle;
use App\Rules\MonotonicOdometer;
use App\Support\Money;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * One endpoint for all four lanes of the quick-add sheet. The shared fields are
 * the spine (odometer + date); the lane-specific ones are conditional.
 */
class StoreEventRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $vehicle = $this->vehicle();

        return [
            'type' => ['required', Rule::enum(EventType::class), ...$this->typeRules()],

            'odometer' => [
                'required', 'integer', 'min:0', 'max:2000000',
                new MonotonicOdometer(
                    $vehicle,
                    $this->string('occurred_on')->value() ?: now()->toDateString(),
                    $this->boolean('confirm_odometer'),
                    $this->ignoredEventId(),
                ),
            ],

            'occurred_on' => ['required', 'date', 'before_or_equal:today'],
            'confirm_odometer' => ['sometimes', 'boolean'],

            'cost' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'location' => ['nullable', 'string', 'max:120'],

            // Fuel lane. The brief's minimum viable fill-up is odometer +
            // gallons + total cost, so both are required on this lane.
            'gallons' => [Rule::requiredIf($this->isType(EventType::Fuel)), 'nullable', 'numeric', 'min:0.001', 'max:200'],
            'full_tank' => ['sometimes', 'boolean'],

            // Expense lane.
            'category' => [Rule::requiredIf($this->isType(EventType::Expense)), 'nullable', 'string', 'max:60'],

            // Service lane: a visit is a shop trip with one or more jobs on it.
            'line_items' => [Rule::requiredIf($this->isType(EventType::Visit)), 'array', 'max:25'],
            'line_items.*.service_type_id' => ['required', 'integer', 'exists:service_types,id'],
            'line_items.*.cost' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'line_items.*.notes' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'line_items.required' => 'Add at least one service to this visit.',
            'gallons.required' => 'Enter how many gallons you put in.',
            'occurred_on.before_or_equal' => 'You cannot log something that has not happened yet.',
        ];
    }

    /**
     * The vehicle the entry belongs to. An edit reads it off the entry, since
     * entries never move between vehicles.
     */
    protected function vehicle(): Vehicle
    {
        /** @var Vehicle */
        return $this->route('vehicle');
    }

    /** The entry being edited, left out of its own odometer check. */
    protected function ignoredEventId(): ?int
    {
        return null;
    }

    /**
     * @return array<int, mixed>
     */
    protected function typeRules(): array
    {
        return [];
    }

    public function eventType(): EventType
    {
        return EventType::from($this->string('type')->value());
    }

    /**
     * Attributes for the Event row, with dollars already converted to cents.
     *
     * @return array<string, mixed>
     */
    public function eventAttributes(): array
    {
        $type = $this->eventType();

        return [
            'type' => $type,
            'odometer' => $this->integer('odometer'),
            'occurred_on' => $this->string('occurred_on')->value(),
            'cost_cents' => Money::toCents($this->input('cost')),
            'notes' => $this->input('notes'),
            'location' => $type === EventType::Visit ? $this->input('location') : null,
            'gallons' => $type === EventType::Fuel ? $this->input('gallons') : null,
            'full_tank' => $type === EventType::Fuel ? $this->boolean('full_tank') : null,
            'category' => $type === EventType::Expense ? $this->input('category') : null,
        ];
    }

    /**
     * @return array<int, array{service_type_id: int, cost_cents: int|null, notes: string|null}>
     */
    public function lineItems(): array
    {
        if ($this->eventType() !== EventType::Visit) {
            return [];
        }

        /** @var array<int, array<string, mixed>> $items */
        $items = $this->input('line_items', []);

        return array_map(fn (array $item): array => [
            'service_type_id' => (int) $item['service_type_id'],
            'cost_cents' => Money::toCents($item['cost'] ?? null),
            'notes' => isset($item['notes']) && is_string($item['notes']) ? $item['notes'] : null,
        ], $items);
    }

    private function isType(EventType $type): bool
    {
        return $this->input('type') === $type->value;
    }
}
