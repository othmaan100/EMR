<?php

namespace App\Http\Controllers;

use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/**
 * The signed-in user's own profile and password.
 */
class AccountController extends Controller
{
    public function edit(Request $request): View
    {
        return view('account.profile', ['user' => $request->user()->load(['department', 'roles'])]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        $user->update($request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:150', Rule::unique('users')->ignore($user)],
            'phone' => ['nullable', 'string', 'max:30'],
        ]));

        return back()->with('success', 'Profile updated.');
    }

    public function forcePassword(Request $request): View|RedirectResponse
    {
        if (! $request->user()->must_change_password) {
            return redirect()->route('dashboard');
        }

        return view('account.force-password');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $user = $request->user();

        $request->validateWithBag('password', [
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', 'different:current_password', Password::defaults()],
        ]);

        $wasForced = $user->must_change_password;

        $user->forceFill([
            'password' => $request->input('password'),
            'must_change_password' => false,
        ])->save();

        Audit::log('password_changed', 'Changed own password', $user);

        return $wasForced
            ? redirect()->route('dashboard')->with('success', 'Password changed. Welcome!')
            : back()->with('success', 'Password changed.');
    }
}
