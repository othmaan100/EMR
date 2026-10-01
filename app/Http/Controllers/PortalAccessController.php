<?php

namespace App\Http\Controllers;

use App\Models\Patient;
use App\Services\PortalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Staff side of the patient portal: issue / reset / disable access.
 */
class PortalAccessController extends Controller
{
    public function __construct(protected PortalService $portal) {}

    public function store(Request $request, Patient $patient): RedirectResponse
    {
        $send = $request->boolean('send_sms') && filled($patient->phone);
        $code = $this->portal->issueAccess($patient, $request->user(), $send);

        // The code is shown once (flashed) and printed on the access letter.
        return redirect()->route('patients.portal.letter', $patient)->with('portal_code', $code)
            ->with('success', 'Portal access code created'.($send ? ' and sent by SMS.' : '.'));
    }

    public function letter(Request $request, Patient $patient): View|RedirectResponse
    {
        $code = $request->session()->get('portal_code');
        if (! $code) {
            return redirect()->route('patients.show', $patient)->with('warning', 'The activation code is only shown once. Create a new code to print another letter.');
        }

        return view('patients.portal-letter', ['patient' => $patient, 'code' => $code, 'hours' => PortalService::CODE_HOURS]);
    }

    public function destroy(Patient $patient): RedirectResponse
    {
        $this->portal->disable($patient);

        return back()->with('success', 'Portal access switched off.');
    }
}
