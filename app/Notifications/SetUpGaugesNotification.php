<?php

namespace App\Notifications;

use App\Models\Vehicle;
use Illuminate\Notifications\Notification;

/**
 * "Welcome to the garage" — waiting in the inbox each time a car is added.
 *
 * A seeded schedule opens as a wall of grey rings: every item is uncalibrated,
 * because we do not know when any of them was last done and guessing would tell
 * someone their oil is overdue when they changed it last week. Nothing on that
 * wall can come due until the owner says otherwise, so without a nudge the
 * product simply sits there looking broken.
 *
 * One per vehicle. It was first-car-only, to keep the inbox quiet, which put it
 * at odds with the vehicle page: every new car shows the "your gauges aren't
 * set" banner, so a second car was being told two different things about
 * itself. The notice belongs to the vehicle, and each vehicle gets one.
 *
 * Database only, no mail. The user is in the app — they just added a car — and
 * an email telling them to go back to the app they are already using is how you
 * teach someone to mute you. Recalls and service reminders keep their email;
 * those arrive when the user is elsewhere and there is something at stake.
 *
 * Deliberately NOT ShouldQueue, unlike those two. They queue because sending
 * mail is external I/O that must not sit in the request; this writes one row.
 * Queueing it would make a notice the user is meant to find on arrival depend
 * on a worker being up — and it would land in `jobs` and stay there, which is
 * exactly what happened when it was first written this way.
 */
class SetUpGaugesNotification extends Notification
{
    public function __construct(private Vehicle $vehicle) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'gauge_setup',
            'vehicle_id' => $this->vehicle->id,
            'vehicle_name' => $this->vehicle->displayName(),
            // Marks the occasion first, asks second. Adding a car is the good
            // part; the gauges are what makes it worth having done, so the
            // reminder rides along rather than leading.
            'title' => 'Welcome to the garage',
            'detail' => 'Set its gauges and it starts counting down to what it needs next',
            'url' => route('vehicles.show', $this->vehicle),
        ];
    }
}
