<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Http\Middleware\PortalSession;
use App\Models\PatientAccount;
use App\Services\PortalService;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function __construct(protected PortalService $portal) {}

    public function showLogin(): View
    {
        return view('portal.auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'hospital_number' => ['required', 'string', 'max:30'],
            'password' => ['required', 'string', 'max:255'],
        ]);

        $account = $this->portal->attempt($data['hospital_number'], $data['password']);
        $this->signIn($request, $account);

        return redirect()->intended(route('portal.dashboard'));
    }

    public function showActivate(): View
    {
        return view('portal.auth.activate');
    }

    public function activate(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'hospital_number' => ['required', 'string', 'max:30'],
            'code' => ['required', 'string', 'max:20'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
        ]);

        $account = $this->portal->activate($data['hospital_number'], $data['code'], $data['password']);
        $this->signIn($request, $account);

        return redirect()->route('portal.dashboard')->with('success', 'Welcome! Your portal account is ready.');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('patient')->logout();
        $request->session()->forget([PortalSession::KEY, 'portal_subject']);
        $request->session()->regenerateToken();

        return redirect()->route('portal.login')->with('success', 'You have signed out.');
    }

    protected function signIn(Request $request, PatientAccount $account): void
    {
        Auth::guard('patient')->login($account);
        $request->session()->regenerate();
        $request->session()->put(PortalSession::KEY, time());
        $request->session()->forget('portal_subject');

        Audit::log('portal_login', 'Patient signed in to the portal', $account->patient);
    }
}
