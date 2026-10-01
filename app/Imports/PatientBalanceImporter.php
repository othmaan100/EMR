<?php

namespace App\Imports;

use App\Imports\Concerns\Lookups;
use App\Models\BillItem;
use App\Models\Patient;
use App\Models\Service;
use App\Services\BillingService;
use Illuminate\Support\Carbon;

class PatientBalanceImporter extends Importer
{
    use Lookups;

    public const SERVICE_CODE = 'OPENING-BAL';

    public function key(): string
    {
        return 'patient-balances';
    }

    public function title(): string
    {
        return 'Patient balances brought forward';
    }

    public function group(): string
    {
        return 'Opening balances & history';
    }

    public function description(): string
    {
        return 'Money patients still owed in the old system. Each becomes an unpaid item on the patient\'s account, payable at the cashier.';
    }

    public function dependsOn(): array
    {
        return ['patients'];
    }

    public function notes(): array
    {
        return [
            'One row per patient, with the total they owe. Credits and deposits held for patients are recorded under Billing → Deposit instead.',
            'Each patient has at most one balance brought forward; updating replaces the amount if nothing has been paid against it yet.',
        ];
    }

    public function matchDescription(): string
    {
        return 'Rows are matched by hospital number (or legacy number) to the patient\'s existing balance brought forward.';
    }

    public function columns(): array
    {
        return [
            new ImportColumn('hospital_number', 'Hospital or legacy number', true, ['string', 'max:50'], setting('patient_number_prefix', 'PT').'-000123', null, null, 'OLD/2015/0456'),
            new ImportColumn('amount', 'Amount owed', true, ['numeric', 'min:0.01', 'max:100000000'], '15000', null, null, '4200.50'),
            new ImportColumn('description', 'Description', false, ['string', 'max:200'], 'Balance brought forward from previous system'),
            new ImportColumn('balance_date', 'Balance as at', false, ['date', 'before_or_equal:today'], '2026-09-30', Values::DATE_HELP),
        ];
    }

    public function normalise(array $row): array
    {
        $row['hospital_number'] = Values::upper($row['hospital_number']);
        $row['amount'] = Values::number($row['amount']);
        $row['balance_date'] = Values::date($row['balance_date']);

        return $row;
    }

    public function check(array &$row): array
    {
        $row['_patient_id'] = $this->patientId($row['hospital_number']);
        if (! $row['_patient_id']) {
            return ["No patient with number \"{$row['hospital_number']}\" — import patients first."];
        }
        $existing = $this->find($row);
        if ($existing && $existing->paid_amount > 0) {
            return ['This patient\'s balance brought forward has payments against it; adjust it in Billing instead.'];
        }

        return [];
    }

    public function rowKey(array $row): ?string
    {
        return (string) $row['_patient_id'];
    }

    public function find(array $row): mixed
    {
        // Look up only: the preview must not create anything.
        $service = Service::where('code', self::SERVICE_CODE)->first();
        if (! $service) {
            return null;
        }

        return BillItem::active()->whereMorphedTo('billable', $service)
            ->whereHas('bill', fn ($q) => $q->where('patient_id', $row['_patient_id']))->first();
    }

    public function save(array $row, mixed $existing): void
    {
        $amount = round((float) $row['amount'], 2);
        $description = $row['description'] ?: 'Balance brought forward from previous system';

        if ($existing) {
            $existing->forceFill(['description' => $description, 'unit_price' => $amount, 'amount' => $amount, 'patient_amount' => $amount])->save();

            return;
        }

        $patient = Patient::findOrFail($row['_patient_id']);
        $bill = app(BillingService::class)->billFor($patient, null);
        $item = new BillItem([
            'description' => $description,
            'quantity' => 1,
            'unit_price' => $amount,
            'amount' => $amount,
            'insurance_amount' => 0,
            'patient_amount' => $amount,
        ]);
        $item->billable()->associate($this->service());
        $item->created_by = $this->user?->id;
        if ($row['balance_date']) {
            $item->created_at = Carbon::parse($row['balance_date'])->setTime(9, 0);
        }
        $bill->items()->save($item);
    }

    protected function service(): Service
    {
        return Service::firstOrCreate(['code' => self::SERVICE_CODE], ['name' => 'Balance brought forward', 'category' => 'Other', 'is_active' => false]);
    }
}
