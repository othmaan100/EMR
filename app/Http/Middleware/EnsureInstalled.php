<?php

namespace App\Http\Middleware;

use App\Support\Installer;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Until setup is complete, every web request is sent to the setup wizard.
 * Afterwards, the wizard itself is locked.
 */
class EnsureInstalled
{
    public function __construct(protected Installer $installer) {}

    public function handle(Request $request, Closure $next): Response
    {
        $inSetup = $request->routeIs('setup.*');

        if (! $this->installer->isInstalled() && ! $inSetup) {
            return redirect()->route('setup.index');
        }

        if ($this->installer->isInstalled() && $inSetup) {
            return redirect()->route('login');
        }

        return $next($request);
    }
}
