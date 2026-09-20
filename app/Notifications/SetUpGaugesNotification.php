<?php

namespace App\Notifications;

use App\Models\Vehicle;
use Illuminate\Notifications\Notification;

/**
 * "Your gauges aren't set yet" — waiting in the inbox once the first car lands.
 *
 * A seeded schedule opens as a wall of grey rings: every item is uncalibrated,
 * because we do not know when any of them was last done and guessing would tell
 * someone their oil is overdue when they changed it last week. Nothing on that
 * wall can come due until the owner says otherwise, so without a nudge the
 * product simply sits there looking broken.
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
            'title' => 'Set up your gauges',
            'detail' => 'Tell us roughly when each service was last done and the countdowns start',
            'url' => route('vehicles.show', $this->vehicle),
        ];
    }
}
