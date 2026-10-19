<?php

namespace App\Http\Middleware;

use App\Support\GarageSummary;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $request->user(),
            ],

            // A closure so it is only evaluated when a page is actually
            // rendered, and never for a guest.
            'garage' => fn (): array => $request->user() === null
                ? GarageSummary::empty()->toArray()
                : GarageSummary::for($request->user())->toArray(),

            // What the signed-in person may do that the UI has to know up
            // front, rather than discover by being refused.
            'can' => fn (): array => [
                'assistedCapture' => $request->user()?->can('use-assisted-capture') ?? false,
            ],

            'unreadNotifications' => fn (): int => $request->user()?->unreadNotifications()->count() ?? 0,
        ];
    }
}
