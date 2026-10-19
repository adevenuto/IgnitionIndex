<?php

namespace App\Providers;

use Anthropic\Client;
use App\Models\User;
use App\Support\Capture\CaptureReader;
use App\Support\Capture\ClaudeCaptureReader;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(CaptureReader::class, fn (): ClaudeCaptureReader => new ClaudeCaptureReader(
            new Client(apiKey: (string) config('services.anthropic.key')),
            Config::string('services.anthropic.model'),
            Config::float('services.anthropic.timeout'),
        ));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureCapture();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }

    /**
     * Assisted capture is per person for now, not per plan: an allowlist while
     * it is being tried, a Pro check once billing exists. Either way this gate
     * is the one place that decides, and the client only ever sees its answer.
     */
    protected function configureCapture(): void
    {
        Gate::define('use-assisted-capture', fn (User $user): bool => filled((string) config('services.anthropic.key'))
            && in_array(strtolower($user->email), array_map(strtolower(...), Config::array('capture.allowed_emails')), true));

        RateLimiter::for('captures', fn (Request $request): Limit => Limit::perDay(Config::integer('capture.daily_limit'))
            ->by((string) $request->user()?->getAuthIdentifier())
            ->response(fn () => response()->json([
                'message' => "That's today's photo and voice allowance. Type it in instead.",
            ], 429)));
    }
}
