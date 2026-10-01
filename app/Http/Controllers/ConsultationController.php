<?php

namespace App\Http\Controllers;

use App\Models\Consultation;
use App\Models\ConsultationAddendum;
use App\Models\Diagnosis;
use App\Models\Drug;
use App\Models\Icd10Code;
use App\Models\ImagingOrder;
use App\Models\ImagingTest;
use App\Models\LabOrder;
use App\Models\LabTest;
use App\Models\Prescription;
use App\Models\PrescriptionItem;
use App\Models\Visit;
use App\Services\ConsultationService;
use App\Services\OrderService;
use App\Support\Audit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ConsultationController extends Controller
{
    public function __construct(protected ConsultationService $service, protected OrderService $orders) {}

    public function show(Request $request, Visit $visit): View
    {
        $user = $request->user();
        $consultation = $this->service->forVisit($visit, $user);
        $patient = $visit->patient;

        if ($consultation) {
            Audit::log('consultation_viewed', "Viewed consultation for {$visit->visit_number}", $consultation);
            $consultation->load(['diagnoses', 'addenda.author', 'doctor', 'labOrders.items.test', 'labOrders.items.results', 'imagingOrders.test', 'prescriptions.items']);
        }

        $latestVitals = $patient->vitalSigns()->valid()->latest('recorded_at')->first()?->setRelation('patient', $patient);

        return view('consultations.show', [
            'visit' => $visit->load('clinic'),
            'patient' => $patient,
            'consultation' => $consultation,
            'editable' => $consultation?->isEditableBy($user) ?? false,
            'latestVitals' => $latestVitals,
            'history' => $patient->consultations()->where('status', 'signed')->whereKeyNot($consultation?->id)
                ->with(['diagnoses', 'doctor', 'visit.clinic'])->latest('signed_at')->limit(10)->get(),
            'recentPrescriptions' => $patient->prescriptions()->with('items')->latest()->limit(5)->get(),
            'labTests' => LabTest::active()->orderBy('category')->orderBy('name')->get()->groupBy('category'),
            'imagingTests' => ImagingTest::active()->orderBy('modality')->orderBy('name')->get()->groupBy('modality'),
            'drugs' => Drug::active()->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, Consultation $consultation): RedirectResponse
    {
        $this->authorizeEdit($request, $consultation);

        $rules = collect(Consultation::SECTIONS)->map(fn () => ['nullable', 'string', 'max:10000'])->all();
        $consultation->update($request->validate($rules));

        return $this->back($consultation, 'notes')->with('success', 'Notes saved.');
    }

    public function addDiagnosis(Request $request, Consultation $consultation): RedirectResponse
    {
        $this->authorizeEdit($request, $consultation);

        $data = $request->validateWithBag('diagnosis', [
            'icd10_code' => ['nullable', 'string', Rule::exists('icd10_codes', 'code')],
            'description' => ['required', 'string', 'max:255'],
            'certainty' => ['required', Rule::in(array_keys(Diagnosis::CERTAINTY))],
            'is_primary' => ['boolean'],
        ]);

        DB::transaction(function () use ($consultation, $data) {
            $isPrimary = (bool) ($data['is_primary'] ?? false) || $consultation->diagnoses()->doesntExist();
            if ($isPrimary) {
                $consultation->diagnoses()->update(['is_primary' => false]);
            }

            $diagnosis = new Diagnosis(['is_primary' => $isPrimary] + $data);
            $diagnosis->patient_id = $consultation->patient_id;
            $consultation->diagnoses()->save($diagnosis);
        });

        return $this->back($consultation, 'diagnoses');
    }

    public function removeDiagnosis(Request $request, Diagnosis $diagnosis): RedirectResponse
    {
        $consultation = $diagnosis->consultation;
        $this->authorizeEdit($request, $consultation);
        $diagnosis->delete();

        return $this->back($consultation, 'diagnoses');
    }

    public function orderLab(Request $request, Consultation $consultation): RedirectResponse
    {
        $this->authorizeEdit($request, $consultation, 'lab.request');

        $data = $request->validateWithBag('lab', OrderService::labRules(), ['tests.required' => 'Select at least one test.']);

        $this->orders->createLabOrder($consultation->patient, $consultation->visit, $consultation, null, $data, $request->user());

        return $this->back($consultation, 'orders')->with('success', count($data['tests']).' lab test(s) requested.');
    }

    public function orderImaging(Request $request, Consultation $consultation): RedirectResponse
    {
        $this->authorizeEdit($request, $consultation, 'imaging.request');

        $data = $request->validateWithBag('imaging', OrderService::imagingRules(),
            ['clinical_notes.required' => 'Give the clinical indication for the radiologist.']);

        $this->orders->createImagingOrder($consultation->patient, $consultation->visit, $consultation, null, $data, $request->user());

        return $this->back($consultation, 'orders')->with('success', 'Imaging requested.');
    }

    public function cancelOrder(Request $request, string $type, int $id): RedirectResponse
    {
        $order = match ($type) {
            'lab' => LabOrder::findOrFail($id),
            'imaging' => ImagingOrder::findOrFail($id),
            default => abort(404),
        };
        $consultation = Consultation::findOrFail($order->consultation_id);
        $this->authorizeEdit($request, $consultation);

        $this->orders->cancel($order, $request->user());

        return $this->back($consultation, 'orders')->with('success', "Order {$order->order_number} cancelled.");
    }

    public function prescribe(Request $request, Consultation $consultation): RedirectResponse
    {
        $this->authorizeEdit($request, $consultation, 'prescriptions.create');

        $data = $request->validateWithBag('rx', OrderService::prescriptionRules());

        $drugName = $this->orders->prescribe($consultation->patient, $consultation->visit, $consultation, null, $data, $request->user());

        return $this->back($consultation, 'orders')->with('success', "{$drugName} added to prescription.");
    }

    public function removePrescriptionItem(Request $request, PrescriptionItem $item): RedirectResponse
    {
        $prescription = $item->prescription;
        $consultation = Consultation::findOrFail($prescription->consultation_id);
        $this->authorizeEdit($request, $consultation);
        abort_unless($prescription->status === 'pending', 422);

        $item->delete();
        if ($prescription->items()->doesntExist()) {
            $prescription->delete();
        }

        return $this->back($consultation, 'orders');
    }

    public function sign(Request $request, Consultation $consultation): RedirectResponse
    {
        $this->authorizeEdit($request, $consultation);

        $followUp = $request->validate([
            'followup_date' => ['nullable', 'date', 'after:today'],
            'followup_time' => ['nullable', 'required_with:followup_date', 'date_format:H:i'],
            'followup_reason' => ['nullable', 'string', 'max:255'],
        ]);

        $appointment = $this->service->sign($consultation, $request->user(), [
            'date' => $followUp['followup_date'] ?? null,
            'time' => $followUp['followup_time'] ?? null,
            'reason' => $followUp['followup_reason'] ?? null,
        ]);

        $message = 'Consultation signed and visit completed.'
            .($appointment ? ' Follow-up booked for '.format_date($appointment->scheduled_at, true).'.' : '');

        return redirect()->route('queue.index', ['mine' => 1])->with('success', $message);
    }

    public function addAddendum(Request $request, Consultation $consultation): RedirectResponse
    {
        abort_unless($consultation->isSigned() && $request->user()->can('consultations.create'), 403);

        $data = $request->validateWithBag('addendum', ['note' => ['required', 'string', 'max:5000']]);

        $addendum = new ConsultationAddendum($data);
        $addendum->user_id = $request->user()->id;
        $consultation->addenda()->save($addendum);

        return $this->back($consultation, 'addenda')->with('success', 'Addendum added.');
    }

    public function printPrescription(Prescription $prescription): View
    {
        return view('consultations.prescription-print', [
            'prescription' => $prescription->load(['items', 'patient', 'prescriber', 'visit.clinic']),
            'diagnoses' => Diagnosis::where('consultation_id', $prescription->consultation_id)->orderByDesc('is_primary')->get(),
        ]);
    }

    public function icd10(Request $request): JsonResponse
    {
        $term = trim((string) $request->query('q'));
        if (mb_strlen($term) < 2) {
            return response()->json([]);
        }

        return response()->json(Icd10Code::search($term)->limit(15)->get(['code', 'description']));
    }

    // ------------------------------------------------------------------

    protected function authorizeEdit(Request $request, ?Consultation $consultation, ?string $permission = null): void
    {
        abort_unless($consultation && $consultation->isEditableBy($request->user()), 403, 'This consultation is signed or belongs to another doctor.');
        abort_if($permission && ! $request->user()->can($permission), 403);
    }

    protected function back(Consultation $consultation, string $anchor): RedirectResponse
    {
        return redirect()->to(route('consultations.show', $consultation->visit_id).'#'.$anchor);
    }
}
