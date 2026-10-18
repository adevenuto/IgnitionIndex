<?php

namespace App\Actions\Events;

use App\Models\Event;
use App\Models\ServiceType;

/**
 * A visit's jobs, written fresh.
 *
 * Shared by logging and editing: an edit replaces the list wholesale rather
 * than diffing it, since line items carry nothing that outlives the visit.
 */
class WriteLineItems
{
    /**
     * @param  array<int, array{service_type_id: int, cost_cents?: int|null, notes?: string|null}>  $lineItems
     * @return list<int> the service types written
     */
    public function handle(Event $event, array $lineItems): array
    {
        $event->lineItems()->delete();

        $known = ServiceType::query()
            ->whereKey(array_column($lineItems, 'service_type_id'))
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        $written = [];

        foreach ($lineItems as $item) {
            if (! in_array($item['service_type_id'], $known, true)) {
                continue;
            }

            $event->lineItems()->create([
                'service_type_id' => $item['service_type_id'],
                'cost_cents' => $item['cost_cents'] ?? null,
                'notes' => $item['notes'] ?? null,
            ]);

            $written[] = $item['service_type_id'];
        }

        return array_values(array_unique($written));
    }
}
