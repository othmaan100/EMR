<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\HospitalProfile;
use DateTimeZone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function edit(Request $request): View
    {
        $section = $request->query('section', 'profile');

        return view('admin.settings.edit', [
            'section' => in_array($section, HospitalProfile::SECTIONS, true) ? $section : 'profile',
            'timezones' => DateTimeZone::listIdentifiers(),
        ]);
    }

    public function update(Request $request, HospitalProfile $profile, string $section): RedirectResponse
    {
        abort_unless(in_array($section, HospitalProfile::SECTIONS, true), 404);

        $profile->save($request, $section);

        return redirect()->route('admin.settings.edit', ['section' => $section])
            ->with('success', 'Settings saved.');
    }
}
