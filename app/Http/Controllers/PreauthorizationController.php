<?php

namespace App\Http\Controllers;

use App\Models\Patient;
use App\Models\Preauthorization;
use App\Services\ClaimService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * HMO pre-authorisation (PA) codes: request, then record the HMO's decision.
 */
class PreauthorizationController extends Controller
{
    public function __construct(protected ClaimService $claims) {}

    public function index(Request $request): View
    {
        $status = array_key_exists($request->query('status'), Preauthorization::STATUSES) ? $request->query('status') : 'requested';

        return view('claims.preauthorizations', [
            'status' => $status,
            'requests' => Preauthorization::with(['patient', 'insuranceProvider', 'bill', 'requester', 'decider'])
                ->where('status', $status)->latest('id')->paginate(30)->withQueryString(),
            'counts' => Preauthorization::selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status'),
        ]);
    }

    public function store(Request $request, Patient $patient): RedirectResponse
    {
        abort_unless($patient->insurance_provider_id && in_array($patient->payment_type, ['insurance', 'corporate'], true), 404);

        $data = $request->validate([
            'bill_id' => ['nullable', Rule::exists('bills', 'id')->where('patient_id', $patient->id)],
            'services' => ['required', 'string', 'max:2000'],
            'diagnosis' => ['nullable', 'string', 'max:255'],
            'amount_requested' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $this->claims->requestAuthorization($data + ['patient_id' => $patient->id, 'insurance_provider_id' => $patient->insurance_provider_id], $request->user());

        return back()->with('success', 'Pre-authorisation request recorded. Enter the code when the HMO replies.');
    }

    public function decide(Request $request, Preauthorization $preauthorization): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['approved', 'declined'])],
            'code' => ['nullable', 'string', 'max:50'],
            'amount_approved' => ['nullable', 'numeric', 'min:0'],
            'valid_until' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $this->claims->decideAuthorization($preauthorization, $data, $request->user());

        return back()->with('success', $data['status'] === 'approved' ? "Approved — code {$data['code']} recorded." : 'Recorded as declined.');
    }
}
