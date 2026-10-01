<?php

namespace App\Services;

use App\Models\Admission;
use App\Models\Consultation;
use App\Models\Drug;
use App\Models\ImagingOrder;
use App\Models\LabOrder;
use App\Models\Patient;
use App\Models\Prescription;
use App\Models\User;
use App\Models\Visit;
use App\Support\AllergyChecker;
use App\Support\Audit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Lab, imaging and prescription orders, whether placed in a clinic
 * consultation or on the ward during an admission. Charges go to the
 * admission's bill when there is one, otherwise the visit's.
 */
class OrderService
{
    public function __construct(protected BillingService $billing) {}

    public static function labRules(): array
    {
        return [
            'tests' => ['required', 'array', 'min:1'],
            'tests.*' => [Rule::exists('lab_tests', 'id')->where('is_active', true)],
            'priority' => ['required', Rule::in(['routine', 'urgent'])],
            'clinical_notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public static function imagingRules(): array
    {
        return [
            'imaging_test_id' => ['required', Rule::exists('imaging_tests', 'id')->where('is_active', true)],
            'priority' => ['required', Rule::in(['routine', 'urgent'])],
            'clinical_notes' => ['required', 'string', 'max:1000'],
        ];
    }

    public static function prescriptionRules(): array
    {
        return [
            'drug' => ['required', 'string', 'max:255'],
            'dose' => ['required', 'string', 'max:50'],
            'route' => ['required', Rule::in(Prescription::ROUTES)],
            'frequency' => ['required', Rule::in(array_keys(Prescription::FREQUENCIES))],
            'duration_value' => ['required', 'integer', 'min:1', 'max:365'],
            'duration_unit' => ['required', Rule::in(['days', 'weeks', 'months', 'dose(s)'])],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'instructions' => ['nullable', 'string', 'max:255'],
            'allergy_override' => ['boolean'],
        ];
    }

    public function createLabOrder(Patient $patient, ?Visit $visit, ?Consultation $consultation, ?Admission $admission, array $data, User $by): LabOrder
    {
        return DB::transaction(function () use ($patient, $visit, $consultation, $admission, $data, $by) {
            $order = $this->stamp(new LabOrder($data), 'LAB', $patient, $visit, $consultation, $admission, $by);
            $items = $order->items()->createMany(collect($data['tests'])->unique()->map(fn ($id) => ['lab_test_id' => $id])->all());
            foreach ($items as $item) {
                $this->billing->charge($patient, $admission ?? $visit, $item->test, $item, 1, $by);
            }

            return $order;
        });
    }

    public function createImagingOrder(Patient $patient, ?Visit $visit, ?Consultation $consultation, ?Admission $admission, array $data, User $by): ImagingOrder
    {
        return DB::transaction(function () use ($patient, $visit, $consultation, $admission, $data, $by) {
            $order = $this->stamp(new ImagingOrder($data), 'IMG', $patient, $visit, $consultation, $admission, $by);
            $this->billing->charge($patient, $admission ?? $visit, $order->test, $order, 1, $by);

            return $order;
        });
    }

    public function cancel(LabOrder|ImagingOrder $order, User $by): void
    {
        abort_unless($order->status === 'requested', 422, 'Only orders not yet started can be cancelled.');

        DB::transaction(function () use ($order, $by) {
            $order->forceFill(['status' => 'cancelled'])->save();
            $reason = "Order {$order->order_number} cancelled";

            if ($order instanceof LabOrder) {
                $order->items()->update(['status' => 'cancelled']);
                $order->items->each(fn ($item) => $this->billing->voidFor($item, $by, $reason));
            } else {
                $this->billing->voidFor($order, $by, $reason);
            }
        });
    }

    /**
     * Add a drug to the open (pending) prescription of this consultation or
     * admission, checking allergies. Returns the drug name used.
     */
    public function prescribe(Patient $patient, ?Visit $visit, ?Consultation $consultation, ?Admission $admission, array $data, User $by): string
    {
        // Match a formulary item by its label; otherwise keep as free text.
        $drug = Drug::active()->get()->first(fn (Drug $d) => strcasecmp($d->label, trim($data['drug'])) === 0);
        $drugName = $drug?->label ?? trim($data['drug']);

        $conflicts = AllergyChecker::conflicts($patient, $drugName);
        if ($conflicts && empty($data['allergy_override'])) {
            throw ValidationException::withMessages([
                'drug' => "ALLERGY ALERT: patient is recorded as allergic to \"".implode('", "', $conflicts).'". Tick the override box to prescribe anyway.',
            ])->errorBag('rx');
        }

        DB::transaction(function () use ($patient, $visit, $consultation, $admission, $data, $by, $drug, $drugName, $conflicts) {
            $open = Prescription::where('status', 'pending')
                ->when($consultation, fn ($q) => $q->where('consultation_id', $consultation->id))
                ->when(! $consultation && $admission, fn ($q) => $q->where('admission_id', $admission->id)->whereNull('consultation_id'))
                ->first();
            $prescription = $open ?? $this->stamp(new Prescription, 'RX', $patient, $visit, $consultation, $admission, $by);

            $prescription->items()->create([
                'drug_id' => $drug?->id,
                'drug_name' => $drugName,
                'dose' => $data['dose'],
                'route' => $data['route'],
                'frequency' => $data['frequency'],
                'duration' => $data['duration_value'].' '.$data['duration_unit'],
                'quantity' => $data['quantity'] ?? null,
                'instructions' => $data['instructions'] ?? null,
                'allergy_override' => (bool) $conflicts,
            ]);

            if ($conflicts) {
                Audit::log('allergy_override', "Prescribed {$drugName} despite recorded allergy (".implode(', ', $conflicts).')', $prescription);
            }
        });

        return $drugName;
    }

    /**
     * Fill the shared order fields and save.
     */
    protected function stamp(Model $order, string $prefix, Patient $patient, ?Visit $visit, ?Consultation $consultation, ?Admission $admission, User $by): Model
    {
        $order->forceFill([
            $prefix === 'RX' ? 'prescription_number' : 'order_number' => ConsultationService::number($prefix),
            'patient_id' => $patient->id,
            'visit_id' => $visit?->id,
            'consultation_id' => $consultation?->id,
            'admission_id' => $admission?->id,
            $prefix === 'RX' ? 'prescribed_by' : 'ordered_by' => $by->id,
        ])->save();

        return $order;
    }
}
