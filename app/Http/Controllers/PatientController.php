<?php

namespace App\Http\Controllers;

use App\Http\Requests\PatientRequest;
use App\Integrations\Identity\NinVerifier;
use App\Models\InsuranceProvider;
use App\Models\Patient;
use App\Models\Surgery;
use App\Services\BillingService;
use App\Services\ImmunizationService;
use App\Support\Audit;
use App\Support\PatientPhoto;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PatientController extends Controller
{
    public function __construct(protected PatientPhoto $photos, protected BillingService $billing) {}

    public function index(Request $request): View|RedirectResponse
    {
        $filters = $request->only(['q', 'gender', 'payment_type', 'registered']);
        $term = trim($filters['q'] ?? '');

        // Scanning a card / typing an exact hospital number opens the folder directly.
        if ($term !== '' && $exact = Patient::where('hospital_number', $term)->first()) {
            return redirect()->route('patients.show', $exact);
        }

        $patients = Patient::with('insuranceProvider')
            ->search($term)
            ->when($filters['gender'] ?? null, fn ($q, $v) => $q->where('gender', $v))
            ->when($filters['payment_type'] ?? null, fn ($q, $v) => $q->where('payment_type', $v))
            ->when(($filters['registered'] ?? null) === 'today', fn ($q) => $q->whereDate('created_at', today()))
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('patients.index', compact('patients', 'filters'));
    }

    /**
     * JSON search for patient pickers (autocomplete).
     */
    public function lookup(Request $request): JsonResponse
    {
        $term = trim((string) $request->query('q'));
        if (mb_strlen($term) < 2) {
            return response()->json([]);
        }

        return response()->json(
            Patient::search($term)->where('is_deceased', false)->orderBy('last_name')->limit(10)->get()
                ->map(fn (Patient $p) => [
                    'id' => $p->id,
                    'hospital_number' => $p->hospital_number,
                    'name' => $p->list_name,
                    'meta' => ucfirst($p->gender).' · '.($p->age ?? '?').($p->phone ? ' · '.$p->phone : ''),
                ])
        );
    }

    public function create(): View
    {
        return view('patients.create', $this->formData(new Patient([
            'payment_type' => 'self_pay',
            'country' => setting('country'),
            'state' => setting('state'),
            'city' => setting('city'),
            'nationality' => setting('country'),
        ])));
    }

    public function store(PatientRequest $request): RedirectResponse
    {
        $data = $request->patientData();

        if (! $request->boolean('confirm_duplicate')) {
            $duplicates = $this->possibleDuplicates($data);

            if ($duplicates->isNotEmpty()) {
                return back()->withInput($request->except(['photo', 'photo_data']))->with('duplicates', $duplicates->map(fn (Patient $p) => [
                    'url' => route('patients.show', $p),
                    'hospital_number' => $p->hospital_number,
                    'name' => $p->full_name,
                    'dob' => format_date($p->date_of_birth),
                    'phone' => $p->phone,
                ])->all());
            }
        }

        $patient = DB::transaction(function () use ($data, $request) {
            $patient = new Patient($data);
            $patient->registered_by = $request->user()->id;
            $patient->save();
            $this->photos->apply($request, $patient);
            $this->applyNinVerification($request, $patient);
            $this->billing->chargeRegistration($patient, $request->user());

            return $patient;
        });

        return redirect()->route('patients.show', $patient)
            ->with('success', "{$patient->full_name} registered with hospital number {$patient->hospital_number}.");
    }

    public function show(Patient $patient): View
    {
        Audit::log('patient_viewed', "Viewed record of {$patient->hospital_number}", $patient);

        return view('patients.show', [
            'patient' => $patient->load(['insuranceProvider', 'registeredBy']),
            'openVisit' => $patient->visits()->open()->with('clinic')->first(),
            'visits' => $patient->visits()->with(['clinic', 'doctor', 'consultation.diagnoses'])->latest('checked_in_at')->limit(10)->get(),
            'problems' => $patient->diagnoses()->where('certainty', 'confirmed')->latest()->get()->unique(fn ($d) => $d->icd10_code ?? $d->description)->take(10),
            'surgeries' => Surgery::where('patient_id', $patient->id)->latest('scheduled_at')->limit(8)->get(),
            'pregnancies' => $patient->gender === 'female' ? $patient->pregnancies()->with('delivery')->get() : collect(),
            'immunizationSummary' => ($patient->ageInYears() !== null && $patient->ageInYears() < 5) || $patient->immunizations()->exists()
                ? app(ImmunizationService::class)->schedule($patient)->countBy('status')
                : null,
            'relatives' => ['mother' => $patient->mother, 'children' => $patient->children()->orderBy('date_of_birth')->get()],
            'admissions' => $patient->admissions()->with(['ward', 'bed'])->latest('admitted_at')->limit(10)->get(),
            'currentAdmission' => $patient->admissions()->current()->with(['ward', 'bed'])->first(),
            'labOrders' => $patient->labOrders()->with(['items.test', 'items.results'])->where('status', '!=', 'cancelled')->latest()->limit(8)->get(),
            'imagingOrders' => $patient->imagingOrders()->with('test')->where('status', '!=', 'cancelled')->latest()->limit(8)->get(),
            'prescriptions' => $patient->prescriptions()->with(['items', 'prescriber'])->latest()->limit(5)->get(),
            'latestVitals' => $patient->vitalSigns()->valid()->with('recorder')->latest('recorded_at')->first()?->setRelation('patient', $patient),
            'notes' => $patient->nursingNotes()->with('author')->latest()->limit(3)->get(),
            'upcoming' => $patient->appointments()->where('status', 'scheduled')->where('scheduled_at', '>=', today())
                ->with(['clinic', 'doctor'])->orderBy('scheduled_at')->get(),
        ]);
    }

    public function edit(Patient $patient): View
    {
        return view('patients.edit', $this->formData($patient));
    }

    public function update(PatientRequest $request, Patient $patient): RedirectResponse
    {
        DB::transaction(function () use ($request, $patient) {
            $patient->update($request->patientData());
            $this->photos->apply($request, $patient);
            $this->applyNinVerification($request, $patient);
        });

        return redirect()->route('patients.show', $patient)->with('success', 'Patient details updated.');
    }

    /**
     * Look up a NIN with the licensed provider; fills the registration form.
     */
    public function ninLookup(Request $request, NinVerifier $verifier): JsonResponse
    {
        $data = $request->validate(['national_id' => ['required', 'string']]);
        $person = $verifier->lookup(preg_replace('/\D/', '', $data['national_id']));

        // Remember the verified NIN until the form is saved.
        $request->session()->put('nin_verified.'.preg_replace('/\D/', '', $data['national_id']), $person['reference']);

        return response()->json($person);
    }

    /**
     * Mark the NIN verified only if this session looked it up; clear it if the NIN changed.
     */
    protected function applyNinVerification(Request $request, Patient $patient): void
    {
        $nin = preg_replace('/\D/', '', (string) $patient->national_id);
        $reference = $nin ? $request->session()->pull('nin_verified.'.$nin) : null;

        if ($reference) {
            $patient->forceFill(['nin_verified_at' => now(), 'nin_verification_ref' => $reference])->save();
        } elseif ($patient->wasChanged('national_id')) {
            $patient->forceFill(['nin_verified_at' => null, 'nin_verification_ref' => null])->save();
        }
    }

    public function destroy(Patient $patient): RedirectResponse
    {
        $patient->delete();

        return redirect()->route('patients.index')->with('success', "Record {$patient->hospital_number} archived.");
    }

    public function photo(Patient $patient): StreamedResponse
    {
        abort_unless($patient->photo && Storage::disk(PatientPhoto::DISK)->exists($patient->photo), 404);

        return Storage::disk(PatientPhoto::DISK)->response($patient->photo, null, [
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }

    public function card(Patient $patient): View
    {
        Audit::log('patient_card_printed', "Printed card for {$patient->hospital_number}", $patient);

        return view('patients.card', ['patient' => $patient->load('insuranceProvider')]);
    }

    /**
     * Same name + date of birth, or same phone number.
     */
    protected function possibleDuplicates(array $data): Collection
    {
        return Patient::query()
            ->where(function ($q) use ($data) {
                $q->where(fn ($q) => $q
                    ->where('first_name', $data['first_name'])
                    ->where('last_name', $data['last_name'])
                    ->whereDate('date_of_birth', $data['date_of_birth']));

                if (! empty($data['phone'])) {
                    $q->orWhere('phone', $data['phone']);
                }
            })
            ->limit(5)
            ->get();
    }

    protected function formData(Patient $patient): array
    {
        return [
            'patient' => $patient,
            'providers' => InsuranceProvider::active()
                ->when($patient->insurance_provider_id, fn ($q, $id) => $q->orWhere('id', $id))
                ->orderBy('name')
                ->get(['id', 'name', 'type']),
        ];
    }
}
