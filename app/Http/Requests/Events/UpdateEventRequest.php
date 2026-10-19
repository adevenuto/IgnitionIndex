<?php

namespace App\Http\Requests\Events;

use App\Models\Event;
use App\Models\Vehicle;
use Illuminate\Validation\Rule;

/**
 * Editing an entry takes the same fields as logging one, checked the same way,
 * with two differences: the entry is left out of its own odometer check, and
 * its type is fixed. A fill-up that should have been a service visit is a
 * different entry, so it is deleted and logged again.
 */
class UpdateEventRequest extends StoreEventRequest
{
    /**
     * Checked before validation, so someone else's entry is a 403 rather than
     * a list of field errors about it.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->entry()->vehicle) ?? false;
    }

    protected function vehicle(): Vehicle
    {
        return $this->entry()->vehicle;
    }

    protected function ignoredEventId(): ?int
    {
        return $this->entry()->id;
    }

    /**
     * @return array<int, mixed>
     */
    protected function typeRules(): array
    {
        return [Rule::in([$this->entry()->type->value])];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            ...parent::messages(),
            'type.in' => 'An entry\'s type can\'t be changed. Delete it and log it again.',
        ];
    }

    private function entry(): Event
    {
        /** @var Event */
        return $this->route('event');
    }
}
