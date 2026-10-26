<?php

namespace App\Http\Controllers\Settings;

use App\Enums\Theme;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\AppearanceUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Settings → Appearance: the colour theme, saved on the account so it follows
 * the person to every device. The page itself applies a choice the moment it
 * is made; this only remembers it.
 */
class AppearanceController extends Controller
{
    public function edit(Request $request): Response
    {
        return Inertia::render('settings/Appearance', [
            'themes' => Theme::options(),
            'current' => $request->user()->colourTheme()->value,
            'default' => Theme::default()->value,
        ]);
    }

    public function update(AppearanceUpdateRequest $request): RedirectResponse
    {
        $request->user()->forceFill([
            'theme' => $request->enum('theme', Theme::class),
        ])->save();

        return to_route('appearance.edit');
    }
}
