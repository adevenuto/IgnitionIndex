<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The in-app inbox: everything we have told this user, in one place.
 *
 * The email is the nudge; this is the record. Without it a reminder that
 * arrives while you are busy is gone, and there is nothing to check later.
 */
class NotificationController extends Controller
{
    public function index(Request $request): Response
    {
        $notifications = $request->user()->notifications()
            ->latest()
            ->paginate(30)
            ->through(fn (DatabaseNotification $notification): array => [
                'id' => $notification->id,
                'read' => $notification->read_at !== null,
                'created_at' => $notification->created_at?->toIso8601String(),
                ...$this->payload($notification),
            ]);

        return Inertia::render('Notifications', [
            'notifications' => $notifications,
            'unreadCount' => $request->user()->unreadNotifications()->count(),
        ]);
    }

    /**
     * Mark one notice read, and optionally follow it to whatever it is about.
     *
     * `?go=1` exists because marking read and going somewhere were two Inertia
     * visits racing each other: the PATCH would cancel the navigation, so the
     * same click sometimes moved and sometimes just sat there. One request does
     * both, and it still works with no JavaScript at all.
     */
    public function update(Request $request, string $notification): RedirectResponse
    {
        $record = $request->user()->notifications()
            ->whereKey($notification)
            ->firstOrFail();

        $record->markAsRead();

        if ($request->boolean('go')) {
            $path = $this->pathOf($record);

            if ($path !== null) {
                return redirect()->to($path);
            }
        }

        return back();
    }

    /**
     * The stored destination as a path on THIS host.
     *
     * Payload urls are absolute, built by route() from APP_URL whenever the
     * notice was written — which is not necessarily the host serving the
     * request now. Redirecting to the absolute url sends an Inertia XHR
     * cross-origin, where it cannot swap the page and the click appears to do
     * nothing. Keeping only the path sidesteps that, and means a destination
     * can never point off-site however the payload was written.
     */
    private function pathOf(DatabaseNotification $notification): ?string
    {
        /** @var array<string, mixed> $data */
        $data = $notification->data;
        $url = is_string($data['url'] ?? null) ? $data['url'] : null;

        if ($url === null) {
            return null;
        }

        $path = parse_url($url, PHP_URL_PATH);

        if (! is_string($path) || $path === '') {
            return null;
        }

        $query = parse_url($url, PHP_URL_QUERY);

        return is_string($query) && $query !== '' ? "{$path}?{$query}" : $path;
    }

    public function readAll(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(DatabaseNotification $notification): array
    {
        /** @var array<string, mixed> $data */
        $data = $notification->data;

        return [
            'kind' => is_string($data['type'] ?? null) ? $data['type'] : 'unknown',
            'vehicle_name' => is_string($data['vehicle_name'] ?? null) ? $data['vehicle_name'] : null,
            'title' => is_string($data['service'] ?? null)
                ? $data['service']
                : (is_string($data['component'] ?? null) ? $data['component'] : 'Update'),
            'detail' => is_string($data['detail'] ?? null) ? $data['detail'] : null,
            'url' => is_string($data['url'] ?? null) ? $data['url'] : null,
        ];
    }
}
