<?php

namespace App\Providers;

use App\Support\Audit;
use App\Support\Installer;
use App\Support\Settings;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Settings::class);
        $this->app->singleton(Installer::class);
    }

    public function boot(): void
    {
        Paginator::useBootstrapFive();

        Password::defaults(fn () => Password::min(8)->letters()->numbers());

        // Super Admin passes every permission check.
        // Patient portal accounts never pass staff permission checks.
        Gate::before(fn ($user) => $user instanceof \App\Models\User && $user->isSuperAdmin() ? true : null);

        $this->applyHospitalSettings();
        $this->registerAuthAuditing();
    }

    /**
     * Push saved hospital settings into runtime config (name, timezone).
     */
    protected function applyHospitalSettings(): void
    {
        if (! $this->app->make(Installer::class)->isInstalled()) {
            return;
        }

        $settings = $this->app->make(Settings::class);
        $timezone = $settings->get('timezone');

        config([
            'app.name' => $settings->get('hospital_name'),
            'app.timezone' => $timezone,
        ]);
        date_default_timezone_set($timezone);
    }

    protected function registerAuthAuditing(): void
    {
        // Staff sign-ins only; the patient portal audits its own (guard "patient").
        Event::listen(Login::class, function (Login $event) {
            if ($event->guard !== 'web') {
                return;
            }

            // Shown on the dashboard so staff notice sign-ins that weren't theirs.
            if ($event->user->last_login_at && request()->hasSession()) {
                request()->session()->put('previous_login', [
                    'at' => $event->user->last_login_at->toIso8601String(),
                    'ip' => $event->user->last_login_ip,
                ]);
            }

            $event->user->forceFill([
                'last_login_at' => now(),
                'last_login_ip' => request()->ip(),
            ])->saveQuietly();

            Audit::log('login', 'Signed in', $event->user, userId: $event->user->getAuthIdentifier());
        });

        Event::listen(Logout::class, function (Logout $event) {
            if ($event->user && $event->guard === 'web') {
                Audit::log('logout', 'Signed out', $event->user, userId: $event->user->getAuthIdentifier());
            }
        });

        Event::listen(Failed::class, function (Failed $event) {
            if ($event->guard !== 'web') {
                return;
            }
            Audit::log('login_failed', 'Failed sign-in for "'.($event->credentials['login'] ?? $event->credentials['username'] ?? $event->credentials['email'] ?? '?').'"', $event->user);
        });
    }
}
