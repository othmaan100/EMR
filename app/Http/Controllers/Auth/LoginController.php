<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'login' => ['required', 'string', 'max:150'],
            'password' => ['required', 'string'],
        ]);

        $throttleKey = Str::transliterate(Str::lower($request->input('login')).'|'.$request->ip());

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            event(new Lockout($request));

            throw ValidationException::withMessages([
                'login' => 'Too many sign-in attempts. Try again in '.RateLimiter::availableIn($throttleKey).' seconds.',
            ]);
        }

        // Staff can sign in with either username or email.
        $field = filter_var($request->input('login'), FILTER_VALIDATE_EMAIL) ? 'email' : 'username';
        $credentials = [$field => $request->input('login'), 'password' => $request->input('password')];
        $account = User::where($field, $request->input('login'))->first();

        if ($account?->locked_until?->isFuture()) {
            throw ValidationException::withMessages([
                'login' => 'This account is locked after too many wrong passwords. Try again after '
                    .$account->locked_until->format('h:i A').' or ask an administrator to unlock it.',
            ]);
        }

        if (! Auth::attempt($credentials + ['is_active' => true], $request->boolean('remember'))) {
            RateLimiter::hit($throttleKey);
            $this->recordFailure($account);

            throw ValidationException::withMessages([
                'login' => 'These credentials do not match an active account.',
            ]);
        }

        RateLimiter::clear($throttleKey);
        $account?->forceFill(['failed_login_attempts' => 0, 'locked_until' => null])->saveQuietly();
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    /**
     * Lock the account after too many consecutive wrong passwords.
     */
    protected function recordFailure(?User $account): void
    {
        if (! $account) {
            return;
        }

        $attempts = $account->failed_login_attempts + 1;
        $max = (int) config('emr.security.max_failed_logins');

        if ($attempts >= $max) {
            $minutes = (int) config('emr.security.lockout_minutes');
            $account->forceFill(['failed_login_attempts' => 0, 'locked_until' => now()->addMinutes($minutes)])->saveQuietly();
            Audit::log('account_locked', "Account \"{$account->username}\" locked for {$minutes} min after {$max} failed sign-ins", $account);

            return;
        }

        $account->forceFill(['failed_login_attempts' => $attempts])->saveQuietly();
    }

    /**
     * Keep-alive from the idle-timeout script while the user is active.
     */
    public function ping(): Response
    {
        return response()->noContent();
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
