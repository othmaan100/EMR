<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Signs users out after a period without activity (Hospital Settings →
 * Security). Background refreshes (X-Requested-With) don't count as activity.
 */
class IdleTimeout
{
    public const KEY = 'last_activity_at';

    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::check()) {
            return $next($request);
        }

        $limit = max(5, (int) setting('session_idle_minutes')) * 60;
        $last = (int) $request->session()->get(self::KEY, time());

        if (time() - $last > $limit) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            $message = 'You were signed out after '.intdiv($limit, 60).' minutes without activity.';
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json(['message' => $message], 401);
            }

            return redirect()->route('login')->with('warning', $message);
        }

        if (! $request->ajax() || $request->routeIs('session.ping')) {
            $request->session()->put(self::KEY, time());
        }

        return $next($request);
    }
}
