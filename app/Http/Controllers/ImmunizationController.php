<?php

namespace App\Http\Controllers;

use App\Models\Patient;
use App\Models\Vaccine;
use App\Services\ImmunizationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ImmunizationController extends Controller
{
    public function __construct(protected ImmunizationService $immunizations) {}

    /**
     * Defaulter tracing list.
     */
    public function index(): View
    {
        return view('immunizations.index', ['defaulters' => $this->immunizations->defaulters()]);
    }

    public function show(Patient $patient): View
    {
        return view('immunizations.show', [
            'patient' => $patient,
            'visit' => null,
            'schedule' => $this->immunizations->schedule($patient->load('immunizations.giver')),
        ]);
    }

    public function store(Request $request, Patient $patient): RedirectResponse
    {
        $data = $request->validate([
            'vaccine_id' => ['required', Rule::exists('vaccines', 'id')->where('is_active', true)],
            'given_on' => ['required', 'date', 'before_or_equal:today'],
            'batch_number' => ['nullable', 'string', 'max:50'],
            'site' => ['nullable', 'string', 'max:30'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        $vaccine = Vaccine::findOrFail($data['vaccine_id']);
        unset($data['vaccine_id']);
        $this->immunizations->record($patient, $vaccine, $data, $request->user());

        return back()->with('success', "{$vaccine->label} recorded.");
    }

    public function card(Patient $patient): View
    {
        return view('immunizations.card', [
            'patient' => $patient,
            'schedule' => $this->immunizations->schedule($patient->load('immunizations')),
        ]);
    }
}
