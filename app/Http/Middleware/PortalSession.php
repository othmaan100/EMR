<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Patient portal gate: the portal must be switched on in Hospital Settings,
 * the account must still be active, and idle sessions end after 15 minutes.
 */
class PortalSession
{
    public const KEY = 'portal_last_activity_at';

    public const IDLE_MINUTES = 15;

    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(setting('portal_enabled'), 404);

        $guard = Auth::guard('patient');
        if ($guard->check()) {
            $expired = time() - (int) $request->session()->get(self::KEY, time()) > self::IDLE_MINUTES * 60;

            if (! $guard->user()->is_active || $expired) {
                $guard->logout();
                $request->session()->forget([self::KEY, 'portal_subject']);
                $request->session()->regenerateToken();

                return redirect()->route('portal.login')->with('warning', $expired
                    ? 'You were signed out after '.self::IDLE_MINUTES.' minutes without activity.'
                    : 'Your portal access has been switched off. Please contact the hospital.');
            }

            $request->session()->put(self::KEY, time());
        }

        return $next($request);
    }
}
