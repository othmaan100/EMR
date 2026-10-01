<?php

namespace App\Http\Controllers;

use App\Models\Patient;
use App\Models\Pregnancy;
use App\Models\Surgery;
use App\Models\SurgicalProcedure;
use App\Models\Theatre;
use App\Models\User;
use App\Services\TheatreService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TheatreController extends Controller
{
    public function __construct(protected TheatreService $theatre) {}

    /**
     * Theatre list for a day, grouped by theatre.
     */
    public function index(Request $request): View
    {
        $date = rescue(fn () => Carbon::parse($request->query('date', today()->toDateString())), today(), false)->startOfDay();

        $surgeries = Surgery::with(['patient', 'surgeon', 'anaesthetist', 'theatre'])
            ->whereDate('scheduled_at', $date)
            ->orderByRaw("CASE urgency WHEN 'emergency' THEN 0 WHEN 'urgent' THEN 1 ELSE 2 END")
            ->orderBy('scheduled_at')->get();

        return view('theatre.index', [
            'date' => $date,
            'theatres' => Theatre::active()->orderBy('name')->get(),
            'surgeries' => $surgeries->groupBy('theatre_id'),
            'inTheatre' => Surgery::where('status', 'in_theatre')->with(['patient', 'theatre'])->get(),
        ]);
    }

    public function create(Request $request, Patient $patient): View
    {
        $pregnancy = $request->integer('pregnancy') ? Pregnancy::find($request->integer('pregnancy')) : null;

        return view('theatre.form', $this->formData(new Surgery([
            'urgency' => $pregnancy ? 'urgent' : 'elective',
            'scheduled_at' => now()->addHour()->startOfHour(),
            'estimated_minutes' => 60,
            'surgical_procedure_id' => $pregnancy ? SurgicalProcedure::where('code', SurgicalProcedure::CAESAREAN)->value('id') : null,
            'pregnancy_id' => $pregnancy?->id,
            'surgeon_id' => $request->user()->hasRole('Doctor') ? $request->user()->id : null,
            'indication' => $pregnancy ? 'G'.$pregnancy->gravida.'P'.$pregnancy->parity.' at '.$pregnancy->gestationLabel().' — ' : null,
        ]), $patient));
    }

    public function store(Request $request, Patient $patient): RedirectResponse
    {
        $surgery = $this->theatre->book($patient, $this->validated($request, $patient), $request->user());

        return redirect()->route('theatre.show', $surgery)
            ->with('success', "{$surgery->procedure_name} booked for {$surgery->scheduled_at->format('d M H:i')} in {$surgery->theatre->name}.");
    }

    public function show(Surgery $surgery): View
    {
        return view('theatre.show', [
            'surgery' => $surgery->load(['patient', 'procedure', 'theatre', 'surgeon', 'anaesthetist', 'assessor', 'observations.recorder', 'admission.ward', 'pregnancy']),
            'patient' => $surgery->patient,
            'visit' => null,
            'latestVitals' => $surgery->patient->vitalSigns()->valid()->latest('recorded_at')->first()?->setRelation('patient', $surgery->patient),
        ]);
    }

    public function edit(Surgery $surgery): View
    {
        return view('theatre.form', $this->formData($surgery, $surgery->patient));
    }

    public function update(Request $request, Surgery $surgery): RedirectResponse
    {
        $this->theatre->reschedule($surgery, $this->validated($request, $surgery->patient));

        return redirect()->route('theatre.show', $surgery)->with('success', 'Booking updated.');
    }

    public function stop(Request $request, Surgery $surgery): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['postponed', 'cancelled'])],
            'reason' => ['required', 'string', 'max:255'],
        ]);
        $this->theatre->stop($surgery, $data['status'], $data['reason']);

        return back()->with('success', "Operation {$data['status']}.");
    }

    public function preop(Request $request, Surgery $surgery): RedirectResponse
    {
        $data = $request->validateWithBag('preop', [
            'consent_signed' => ['boolean'],
            'fasting_confirmed' => ['boolean'],
            'site_marked' => ['boolean'],
            'asa_grade' => ['nullable', 'integer', 'between:1,6'],
            'blood_units_available' => ['nullable', 'integer', 'between:0,20'],
            'preop_notes' => ['nullable', 'string', 'max:3000'],
        ]);
        $this->theatre->preop($surgery, $data, $request->user());

        return redirect()->to(route('theatre.show', $surgery).'#preop')->with(
            $surgery->status === 'ready' ? 'success' : 'warning',
            $surgery->status === 'ready' ? 'Pre-op assessment complete — ready for theatre.' : 'Saved. Consent, fasting and ASA grade are needed before theatre.'
        );
    }

    public function checklist(Request $request, Surgery $surgery, string $phase): RedirectResponse
    {
        abort_unless(array_key_exists($phase, Surgery::CHECKLIST), 404);
        $data = $request->validate(['items' => ['array'], 'items.*' => ['integer']]);

        $this->theatre->checklist($surgery, $phase, $data['items'] ?? [], $request->user());

        return redirect()->to(route('theatre.show', $surgery).'#checklist')->with('success', 'WHO '.Surgery::CHECKLIST[$phase]['title'].' completed.');
    }

    public function observe(Request $request, Surgery $surgery): RedirectResponse
    {
        $data = $request->validateWithBag('obs', [
            'pulse' => ['nullable', 'integer', 'between:20,250'],
            'systolic' => ['nullable', 'integer', 'between:40,280'],
            'diastolic' => ['nullable', 'integer', 'between:20,180'],
            'spo2' => ['nullable', 'integer', 'between:50,100'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);
        $obs = $this->theatre->observe($surgery, $data, $request->user());

        return redirect()->to(route('theatre.show', $surgery).'#anaesthesia')->with($obs->isAbnormal() ? 'warning' : 'success', $obs->isAbnormal() ? 'Recorded — abnormal value.' : 'Observation recorded.');
    }

    public function anaesthesia(Request $request, Surgery $surgery): RedirectResponse
    {
        $data = $request->validateWithBag('anaes', [
            'anaesthesia_type' => ['nullable', Rule::in(array_keys(Surgery::ANAESTHESIA))],
            'airway' => ['nullable', 'string', 'max:50'],
            'anaesthesia_drugs' => ['nullable', 'string', 'max:3000'],
            'fluids' => ['nullable', 'string', 'max:2000'],
            'anaesthesia_notes' => ['nullable', 'string', 'max:3000'],
        ]);
        $this->theatre->anaesthesia($surgery, $data);

        return redirect()->to(route('theatre.show', $surgery).'#anaesthesia')->with('success', 'Anaesthesia record saved.');
    }

    public function complete(Request $request, Surgery $surgery): RedirectResponse
    {
        $data = $request->validateWithBag('opnote', [
            'findings' => ['required', 'string', 'max:5000'],
            'procedure_performed' => ['required', 'string', 'max:5000'],
            'blood_loss_ml' => ['nullable', 'integer', 'between:0,20000'],
            'specimens' => ['nullable', 'string', 'max:255'],
            'implants' => ['nullable', 'string', 'max:255'],
            'drains' => ['nullable', 'string', 'max:255'],
            'closure' => ['nullable', 'string', 'max:255'],
            'complications' => ['nullable', 'string', 'max:3000'],
            'postop_orders' => ['required', 'string', 'max:5000'],
        ]);
        $this->theatre->complete($surgery, $data, $request->user());

        return redirect()->route('theatre.show', $surgery)->with('success', 'Operation note signed. Operation completed.');
    }

    public function note(Surgery $surgery): View
    {
        return view('theatre.note', ['surgery' => $surgery->load(['patient', 'theatre', 'surgeon', 'anaesthetist', 'observations', 'admission.ward'])]);
    }

    protected function validated(Request $request, Patient $patient): array
    {
        $data = $request->validate([
            'surgical_procedure_id' => ['nullable', 'required_without:procedure_name', Rule::exists('surgical_procedures', 'id')->where('is_active', true)],
            'procedure_name' => ['nullable', 'string', 'max:255'],
            'theatre_id' => ['required', Rule::exists('theatres', 'id')->where('is_active', true)],
            'surgeon_id' => ['nullable', Rule::exists('users', 'id')->where('is_active', true)],
            'assistant' => ['nullable', 'string', 'max:255'],
            'anaesthetist_id' => ['nullable', Rule::exists('users', 'id')->where('is_active', true)],
            'urgency' => ['required', Rule::in(array_keys(Surgery::URGENCY))],
            'scheduled_at' => ['required', 'date'],
            'estimated_minutes' => ['required', 'integer', 'between:5,1440'],
            'indication' => ['required', 'string', 'max:2000'],
            'pregnancy_id' => ['nullable', Rule::exists('pregnancies', 'id')->where('patient_id', $patient->id)],
        ], ['surgical_procedure_id.required_without' => 'Choose a procedure or type its name.']);

        // Elective cases can't be booked in the past; emergencies are often entered after the fact.
        if ($data['urgency'] === 'elective' && Carbon::parse($data['scheduled_at'])->lt(now()->subMinutes(5))) {
            throw \Illuminate\Validation\ValidationException::withMessages(['scheduled_at' => 'Elective operations cannot be booked in the past.']);
        }

        return $data;
    }

    protected function formData(Surgery $surgery, Patient $patient): array
    {
        $clinicians = User::active()->whereHas('roles', fn ($q) => $q->whereIn('name', ['Doctor', 'Anaesthetist']))->with('roles')->orderBy('name')->get();

        return [
            'surgery' => $surgery,
            'patient' => $patient,
            'visit' => null,
            'procedures' => SurgicalProcedure::active()->orderBy('specialty')->orderBy('name')->get(),
            'theatres' => Theatre::active()->orderBy('name')->pluck('name', 'id'),
            'surgeons' => $clinicians->filter(fn ($u) => $u->hasRole('Doctor'))->pluck('name', 'id'),
            'anaesthetists' => $clinicians->pluck('name', 'id'),
        ];
    }
}
