<?php

namespace App\Http\Controllers;

use App\Models\Admission;
use App\Models\AdmissionNote;
use App\Models\Bed;
use App\Models\Drug;
use App\Models\ImagingOrder;
use App\Models\ImagingTest;
use App\Models\LabOrder;
use App\Models\LabTest;
use App\Models\MedicationAdministration;
use App\Models\Patient;
use App\Models\PrescriptionItem;
use App\Models\User;
use App\Models\Visit;
use App\Models\Ward;
use App\Services\BillingService;
use App\Services\InpatientService;
use App\Services\OrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class InpatientController extends Controller
{
    public function __construct(protected InpatientService $inpatients, protected OrderService $orders) {}

    /**
     * Bed board.
     */
    public function index(Request $request): View
    {
        $wards = Ward::active()->with(['beds.currentAdmission.patient'])->orderBy('name')->get();
        $wardId = $request->integer('ward_id') ?: null;

        return view('inpatients.index', [
            'wards' => $wards,
            'shown' => $wardId ? $wards->where('id', $wardId) : $wards,
            'wardId' => $wardId,
            'admissions' => Admission::current()->with(['patient', 'ward', 'bed', 'doctor'])
                ->when($wardId, fn ($q) => $q->where('ward_id', $wardId))->orderBy('admitted_at')->get(),
        ]);
    }

    public function create(Request $request, Patient $patient): View
    {
        $visit = $request->integer('visit') ? Visit::with('consultation.diagnoses')->find($request->integer('visit')) : null;

        return view('inpatients.admit', [
            'patient' => $patient,
            'visit' => $visit,
            'reason' => $visit?->consultation?->diagnoses->pluck('description')->implode('; ') ?: $visit?->complaint,
            'wards' => Ward::active()->with(['beds' => fn ($q) => $q->where('status', 'available')])->orderBy('name')->get()
                ->filter(fn (Ward $w) => $w->accepts($patient)),
            'doctors' => User::role('Doctor')->active()->orderBy('name')->pluck('name', 'id'),
            'current' => Admission::current()->where('patient_id', $patient->id)->first(),
        ]);
    }

    public function store(Request $request, Patient $patient): RedirectResponse
    {
        $data = $request->validate([
            'bed_id' => ['required', Rule::exists('beds', 'id')],
            'doctor_id' => ['nullable', Rule::exists('users', 'id')],
            'reason' => ['required', 'string', 'max:2000'],
            'visit_id' => ['nullable', Rule::exists('visits', 'id')->where('patient_id', $patient->id)],
        ]);

        $admission = $this->inpatients->admit($patient, Bed::findOrFail($data['bed_id']), $data, $request->user(),
            isset($data['visit_id']) ? Visit::find($data['visit_id']) : null);

        return redirect()->route('inpatients.show', $admission)
            ->with('success', "{$patient->full_name} admitted to {$admission->ward->name}, bed {$admission->bed->label}.");
    }

    public function show(Admission $admission, BillingService $billing): View
    {
        $admission->load(['patient', 'ward', 'bed', 'doctor', 'admittedBy', 'dischargedBy', 'notes.author', 'movements.fromBed.ward', 'movements.toBed.ward', 'movements.user',
            'prescriptions.items.drug', 'labOrders.items.test', 'labOrders.items.results', 'imagingOrders.test', 'bill.items']);
        $patient = $admission->patient;

        $items = $admission->prescriptions->flatMap->items;

        return view('inpatients.show', [
            'admission' => $admission,
            'patient' => $patient,
            'visit' => null,
            'medications' => $items->sortBy(fn (PrescriptionItem $i) => [$i->stopped_at ? 1 : 0, $i->id]),
            'doses' => MedicationAdministration::where('admission_id', $admission->id)->with(['item', 'user'])->latest('administered_at')->limit(50)->get(),
            'lastDose' => MedicationAdministration::where('admission_id', $admission->id)->where('status', 'given')
                ->selectRaw('prescription_item_id, MAX(administered_at) as last')->groupBy('prescription_item_id')->pluck('last', 'prescription_item_id'),
            'latestVitals' => $patient->vitalSigns()->valid()->latest('recorded_at')->first()?->setRelation('patient', $patient),
            'freeBeds' => Ward::active()->with(['beds' => fn ($q) => $q->where('status', 'available')])->get()->filter(fn (Ward $w) => $w->accepts($patient)),
            'labTests' => LabTest::active()->orderBy('category')->orderBy('name')->get()->groupBy('category'),
            'imagingTests' => ImagingTest::active()->orderBy('modality')->orderBy('name')->get()->groupBy('modality'),
            'drugs' => Drug::active()->orderBy('name')->get(),
            'billTotals' => $admission->bill?->totals(),
            'deposit' => $billing->depositBalance($patient),
            'outstanding' => $patient->outstandingBalance(),
        ]);
    }

    public function transfer(Request $request, Admission $admission): RedirectResponse
    {
        $data = $request->validate([
            'bed_id' => ['required', Rule::exists('beds', 'id')],
            'reason' => ['required', 'string', 'max:255'],
        ]);
        $this->inpatients->transfer($admission, Bed::findOrFail($data['bed_id']), $data['reason'], $request->user());

        return back()->with('success', "Moved to {$admission->fresh('ward')->ward->name}, bed {$admission->fresh('bed')->bed->label}.");
    }

    public function note(Request $request, Admission $admission): RedirectResponse
    {
        $data = $request->validateWithBag('note', [
            'type' => ['required', Rule::in(array_keys(AdmissionNote::TYPES))],
            'note' => ['required', 'string', 'max:5000'],
        ]);

        $note = new AdmissionNote($data);
        $note->user_id = $request->user()->id;
        $admission->notes()->save($note);

        return $this->back($admission, 'notes')->with('success', 'Note added.');
    }

    public function prescribe(Request $request, Admission $admission): RedirectResponse
    {
        abort_unless($admission->isCurrent(), 422);
        $data = $request->validateWithBag('rx', OrderService::prescriptionRules());

        $drug = $this->orders->prescribe($admission->patient, null, null, $admission, $data, $request->user());

        return $this->back($admission, 'medications')->with('success', "{$drug} added to the drug chart and sent to pharmacy.");
    }

    public function stopMedication(Request $request, PrescriptionItem $item): RedirectResponse
    {
        $admission = Admission::findOrFail($item->prescription->admission_id);
        $item->forceFill(['stopped_at' => now(), 'stopped_by' => $request->user()->id])->save();

        return $this->back($admission, 'medications')->with('success', "{$item->drug_name} stopped.");
    }

    public function administer(Request $request, Admission $admission): RedirectResponse
    {
        abort_unless($admission->isCurrent(), 422);
        $data = $request->validateWithBag('mar', [
            'prescription_item_id' => ['required', Rule::exists('prescription_items', 'id')->whereNull('stopped_at')],
            'status' => ['required', Rule::in(array_keys(MedicationAdministration::STATUSES))],
            'dose_given' => ['nullable', 'string', 'max:50'],
            'note' => ['nullable', 'required_unless:status,given', 'string', 'max:255'],
        ], ['note.required_unless' => 'Give a reason when a dose is refused or held.']);

        $item = PrescriptionItem::with('prescription')->findOrFail($data['prescription_item_id']);
        abort_unless($item->prescription->admission_id === $admission->id, 422);

        $dose = new MedicationAdministration($data + ['administered_at' => now()]);
        $dose->user_id = $request->user()->id;
        $admission->administrations()->save($dose);

        return $this->back($admission, 'medications')->with('success', "{$item->drug_name}: ".MedicationAdministration::STATUSES[$data['status']]['label'].' recorded.');
    }

    public function orderLab(Request $request, Admission $admission): RedirectResponse
    {
        abort_unless($admission->isCurrent(), 422);
        $data = $request->validateWithBag('lab', OrderService::labRules(), ['tests.required' => 'Select at least one test.']);
        $this->orders->createLabOrder($admission->patient, null, null, $admission, $data, $request->user());

        return $this->back($admission, 'orders')->with('success', count($data['tests']).' lab test(s) requested.');
    }

    public function orderImaging(Request $request, Admission $admission): RedirectResponse
    {
        abort_unless($admission->isCurrent(), 422);
        $data = $request->validateWithBag('imaging', OrderService::imagingRules());
        $this->orders->createImagingOrder($admission->patient, null, null, $admission, $data, $request->user());

        return $this->back($admission, 'orders')->with('success', 'Imaging requested.');
    }

    public function cancelOrder(Request $request, string $type, int $id): RedirectResponse
    {
        $order = $type === 'lab' ? LabOrder::findOrFail($id) : ImagingOrder::findOrFail($id);
        abort_unless($order->admission_id, 404);
        $this->orders->cancel($order, $request->user());

        return $this->back(Admission::findOrFail($order->admission_id), 'orders')->with('success', "Order {$order->order_number} cancelled.");
    }

    public function chargeBeds(Request $request, Admission $admission): RedirectResponse
    {
        $days = DB::transaction(fn () => $this->inpatients->chargeBeds($admission, today(), $request->user()));

        return back()->with('success', $days ? "{$days} bed-day(s) charged." : 'Bed charges are up to date.');
    }

    public function dischargeForm(Admission $admission, BillingService $billing): View
    {
        return view('inpatients.discharge', [
            'admission' => $admission->load(['patient', 'ward', 'bed', 'prescriptions.items']),
            'patient' => $admission->patient,
            'visit' => null,
            'outstanding' => $admission->patient->outstandingBalance(),
        ]);
    }

    public function discharge(Request $request, Admission $admission): RedirectResponse
    {
        $data = $request->validate([
            'discharge_type' => ['required', Rule::in(array_keys(Admission::DISCHARGE_TYPES))],
            'final_diagnosis' => ['required', 'string', 'max:2000'],
            'discharge_summary' => ['required', 'string', 'max:10000'],
            'discharge_medications' => ['nullable', 'string', 'max:3000'],
            'follow_up' => ['nullable', 'string', 'max:2000'],
            'balance_acknowledged' => ['boolean'],
        ]);

        if ($admission->patient->outstandingBalance() > 0 && ! $request->boolean('balance_acknowledged')) {
            return back()->withInput()->withErrors(['balance_acknowledged' => 'The patient still owes money. Confirm to discharge anyway, or send them to the cashier first.']);
        }

        unset($data['balance_acknowledged']);
        $this->inpatients->discharge($admission, $data, $request->user());

        return redirect()->route('inpatients.summary', $admission)->with('success', 'Patient discharged.');
    }

    public function summary(Admission $admission): View
    {
        return view('inpatients.summary', [
            'admission' => $admission->load(['patient', 'ward', 'bed', 'doctor', 'dischargedBy',
                'labOrders.items.test', 'labOrders.items.results', 'imagingOrders.test']),
        ]);
    }

    protected function back(Admission $admission, string $anchor): RedirectResponse
    {
        return redirect()->to(route('inpatients.show', $admission).'#'.$anchor);
    }
}
