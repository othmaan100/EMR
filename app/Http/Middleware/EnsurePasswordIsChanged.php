<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Users with a temporary password (new account or admin reset) must set
 * their own before using the system.
 */
class EnsurePasswordIsChanged
{
    protected const ALLOWED_ROUTES = ['password.force', 'account.password.update', 'logout'];

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->must_change_password && ! $request->routeIs(self::ALLOWED_ROUTES)) {
            return redirect()->route('password.force');
        }

        return $next($request);
    }
}
