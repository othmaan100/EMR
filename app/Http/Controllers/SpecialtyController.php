<?php

namespace App\Http\Controllers;

use App\Models\DentalFinding;
use App\Models\EyeExam;
use App\Models\Patient;
use App\Models\PhysioEpisode;
use App\Models\Service;
use App\Services\SpecialtyService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Dental chart, eye examinations and physiotherapy, opened from the
 * patient folder or from a consultation in a specialty clinic.
 */
class SpecialtyController extends Controller
{
    public function __construct(protected SpecialtyService $specialty) {}

    // ------------------------------------------------------------------ dental

    public function dental(Patient $patient): View
    {
        $isChild = $patient->ageInYears() !== null && $patient->ageInYears() < 13;

        return view('specialty.dental', [
            'patient' => $patient,
            'findings' => DentalFinding::with(['service', 'recorder', 'completer'])->where('patient_id', $patient->id)->latest('id')->get(),
            'toothMap' => $this->specialty->toothMap($patient),
            'showPrimary' => $isChild,
            'services' => Service::active()->where('category', 'Dental')->orderBy('name')->pluck('name', 'id'),
        ]);
    }

    public function storeDental(Request $request, Patient $patient): RedirectResponse
    {
        $data = $request->validate([
            'tooth' => ['nullable', 'integer', Rule::in($this->allTeeth())],
            'surfaces' => ['nullable', 'array'],
            'surfaces.*' => [Rule::in(array_keys(config('emr.specialty.surfaces')))],
            'condition' => ['nullable', Rule::in(array_keys(config('emr.specialty.dental_conditions')))],
            'status' => ['required', Rule::in(['existing', 'planned', 'completed'])],
            'service_id' => ['nullable', Rule::exists('services', 'id')->where('category', 'Dental')],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);
        $data['surfaces'] = ! empty($data['surfaces']) ? implode('', $data['surfaces']) : null;

        $this->specialty->recordDental($patient, $data, $request->user());

        return back()->with('success', 'Dental chart updated.');
    }

    public function completeDental(Request $request, DentalFinding $finding): RedirectResponse
    {
        $this->specialty->completeDental($finding, $request->user());

        return back()->with('success', 'Treatment marked done'.($finding->service ? ' and charged (if priced).' : '.'));
    }

    public function cancelDental(Request $request, DentalFinding $finding): RedirectResponse
    {
        $this->specialty->cancelDental($finding, $request->user());

        return back()->with('success', 'Planned treatment cancelled.');
    }

    // ------------------------------------------------------------------ eye

    public function eye(Patient $patient): View
    {
        return view('specialty.eye', [
            'patient' => $patient,
            'exams' => EyeExam::with('examiner')->where('patient_id', $patient->id)->latest('id')->get(),
        ]);
    }

    public function storeEye(Request $request, Patient $patient): RedirectResponse
    {
        $va = ['nullable', Rule::in(config('emr.specialty.snellen'))];
        $data = $request->validate([
            'va_right' => $va, 'va_left' => $va, 'va_right_corrected' => $va, 'va_left_corrected' => $va,
            'iop_right' => ['nullable', 'numeric', 'between:0,80'], 'iop_left' => ['nullable', 'numeric', 'between:0,80'],
            'sph_right' => ['nullable', 'numeric', 'between:-30,30'], 'sph_left' => ['nullable', 'numeric', 'between:-30,30'],
            'cyl_right' => ['nullable', 'numeric', 'between:-10,10'], 'cyl_left' => ['nullable', 'numeric', 'between:-10,10'],
            'axis_right' => ['nullable', 'integer', 'between:0,180', 'required_with:cyl_right'],
            'axis_left' => ['nullable', 'integer', 'between:0,180', 'required_with:cyl_left'],
            'add_right' => ['nullable', 'numeric', 'between:0,4'], 'add_left' => ['nullable', 'numeric', 'between:0,4'],
            'anterior_right' => ['nullable', 'string', 'max:1000'], 'anterior_left' => ['nullable', 'string', 'max:1000'],
            'fundus_right' => ['nullable', 'string', 'max:1000'], 'fundus_left' => ['nullable', 'string', 'max:1000'],
            'pd' => ['nullable', 'integer', 'between:40,80'],
            'diagnosis' => ['nullable', 'string', 'max:255'],
            'plan' => ['nullable', 'string', 'max:2000'],
            'spectacles_prescribed' => ['boolean'],
            'lens_notes' => ['nullable', 'string', 'max:255'],
            'charge_refraction' => ['boolean'],
        ], [], ['axis_right' => 'right axis', 'axis_left' => 'left axis']);

        $charge = (bool) ($data['charge_refraction'] ?? false);
        unset($data['charge_refraction']);
        $exam = $this->specialty->recordEye($patient, $data, $request->user(), $charge);

        return back()->with($exam->alerts() ? 'warning' : 'success', $exam->alerts()
            ? 'Saved. Please note: '.implode('; ', $exam->alerts()).'.'
            : 'Eye examination saved.');
    }

    public function spectacles(EyeExam $exam): View
    {
        abort_unless($exam->spectacles_prescribed, 404);

        return view('specialty.spectacles', ['exam' => $exam->load(['patient', 'examiner'])]);
    }

    // ------------------------------------------------------------------ physiotherapy

    public function physio(Patient $patient): View
    {
        return view('specialty.physio', [
            'patient' => $patient,
            'episodes' => PhysioEpisode::withCount('sessions')->where('patient_id', $patient->id)->latest('id')->get(),
        ]);
    }

    public function storePhysio(Request $request, Patient $patient): RedirectResponse
    {
        $data = $request->validate([
            'region' => ['required', 'string', 'max:100'],
            'complaint' => ['required', 'string', 'max:2000'],
            'assessment' => ['nullable', 'string', 'max:5000'],
            'pain_initial' => ['nullable', 'integer', 'between:0,10'],
            'goals' => ['nullable', 'string', 'max:2000'],
            'plan' => ['nullable', 'string', 'max:2000'],
            'sessions_planned' => ['nullable', 'integer', 'between:1,60'],
        ]);

        $episode = $this->specialty->startPhysio($patient, $data, $request->user());

        return redirect()->route('specialty.physio.show', $episode)->with('success', 'Assessment saved. Record each session below.');
    }

    public function physioEpisode(PhysioEpisode $episode): View
    {
        $episode->load(['patient', 'sessions.therapist', 'creator']);
        $sessions = $episode->sessions;
        $chart = $sessions->whereNotNull('pain_after')->count() >= 2 ? [
            'id' => 'pain', 'title' => 'Pain score', 'unit' => 'out of 10', 'min' => 0, 'max' => 10,
            'labels' => $sessions->map(fn ($s) => $s->session_date->format('d M'))->values()->all(),
            'series' => [
                ['label' => 'Before session', 'data' => $sessions->pluck('pain_before')->values()->all()],
                ['label' => 'After session', 'data' => $sessions->pluck('pain_after')->values()->all()],
            ],
        ] : null;

        return view('specialty.physio-episode', ['episode' => $episode, 'patient' => $episode->patient, 'chart' => $chart]);
    }

    public function storeSession(Request $request, PhysioEpisode $episode): RedirectResponse
    {
        $data = $request->validate([
            'session_date' => ['required', 'date', 'before_or_equal:today'],
            'treatments' => ['required', 'array', 'min:1'],
            'treatments.*' => [Rule::in(array_keys(config('emr.specialty.physio_treatments')))],
            'pain_before' => ['nullable', 'integer', 'between:0,10'],
            'pain_after' => ['nullable', 'integer', 'between:0,10'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ], ['treatments.required' => 'Tick at least one treatment given.']);

        $this->specialty->addSession($episode, $data, $request->user());

        return back()->with('success', 'Session recorded.');
    }

    public function discharge(Request $request, PhysioEpisode $episode): RedirectResponse
    {
        $data = $request->validate([
            'outcome' => ['required', Rule::in(array_keys(config('emr.specialty.physio_outcomes')))],
            'discharge_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $this->specialty->discharge($episode, $data, $request->user());

        return back()->with('success', 'Discharged from physiotherapy.');
    }

    /**
     * @return list<int>
     */
    protected function allTeeth(): array
    {
        return collect(config('emr.specialty.teeth'))->flatten()->all();
    }
}
