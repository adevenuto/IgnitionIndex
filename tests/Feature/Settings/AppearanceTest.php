<?php

use App\Enums\Theme;
use App\Models\User;

test('the appearance page lists every theme with the current one marked', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('appearance.edit'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('settings/Appearance')
            ->has('themes', count(Theme::cases()))
            ->where('current', Theme::default()->value)
            ->where('default', Theme::default()->value));
});

test('a theme can be chosen and is saved to the account', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patch(route('appearance.update'), ['theme' => 'lagoon'])
        ->assertRedirect(route('appearance.edit'));

    expect($user->refresh()->theme)->toBe(Theme::Lagoon);

    $this->get(route('appearance.edit'))
        ->assertInertia(fn ($page) => $page->where('current', 'lagoon'));
});

test('only real themes are accepted', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patch(route('appearance.update'), ['theme' => 'vaporwave'])
        ->assertSessionHasErrors('theme');

    expect($user->refresh()->theme)->toBeNull();
});

test('guests cannot change a theme', function () {
    $this->patch(route('appearance.update'), ['theme' => 'neon'])
        ->assertRedirect(route('login'));
});

test('the page is rendered in the default theme for guests', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee('data-theme="volt"', false)
        ->assertSee('data-scheme="dark"', false);
});

test('the page is rendered in the theme a person chose', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $user->forceFill(['theme' => Theme::Sandstone])->save();

    $this->actingAs($user)
        ->get(route('appearance.edit'))
        ->assertSee('data-theme="sandstone"', false)
        ->assertSee('data-scheme="light"', false)
        ->assertSee('#f4f1ec', false);
});
