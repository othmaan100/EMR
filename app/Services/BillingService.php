<?php

namespace App\Services;

use App\Models\Admission;
use App\Models\Bill;
use App\Models\BillItem;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\Service;
use App\Models\User;
use App\Models\Visit;
use App\Support\Audit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BillingService
{
    /**
     * The bill for a visit or an admission (created on first charge). Charges
     * outside either, like registration, go on a bill of their own.
     */
    public function billFor(Patient $patient, Visit|Admission|null $context): Bill
    {
        $column = $context instanceof Admission ? 'admission_id' : 'visit_id';

        if ($context && $bill = Bill::where($column, $context->id)->first()) {
            return $bill;
        }

        $bill = new Bill;
        $bill->bill_number = ConsultationService::number('BILL');
        $bill->patient_id = $patient->id;
        if ($context) {
            $bill->{$column} = $context->id;
        }
        $bill->claim_status = 'none'; // set explicitly: the DB default isn't visible on a new model
        $bill->save();

        return $bill;
    }

    /**
     * Add a charge. Returns null when the item has no price (not billed).
     */
    public function charge(Patient $patient, Visit|Admission|null $visit, Model $billable, ?Model $source, float $quantity, ?User $by, ?string $description = null): ?BillItem
    {
        $provider = in_array($patient->payment_type, ['insurance', 'corporate'], true) ? $patient->insuranceProvider : null;
        $unitPrice = $billable->priceFor($provider?->id);

        if ($unitPrice === null) {
            return null;
        }

        $amount = round($unitPrice * $quantity, 2);
        $insurance = $provider ? round($amount * $provider->coverage_percent / 100, 2) : 0.0;

        $bill = $this->billFor($patient, $visit);

        $item = new BillItem([
            'description' => $description ?? $billable->billingLabel(),
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'amount' => $amount,
            'insurance_amount' => $insurance,
            'patient_amount' => round($amount - $insurance, 2),
        ]);
        $item->billable()->associate($billable);
        if ($source) {
            $item->source()->associate($source);
        }
        $item->created_by = $by?->id;

        // "Free / waiver" patients: patient share written off automatically.
        if ($patient->payment_type === 'free' && $item->patient_amount > 0) {
            $item->discount_amount = $item->patient_amount;
            $item->discount_reason = 'Free / waiver patient';
        }

        $bill->items()->save($item);

        if ($insurance > 0 && $bill->claim_status === 'none') {
            $bill->forceFill(['insurance_provider_id' => $provider->id, 'claim_status' => 'pending'])->save();
        }

        return $item;
    }

    public function chargeRegistration(Patient $patient, ?User $by): ?BillItem
    {
        $service = Service::active()->where('code', Service::REGISTRATION)->first();

        return $service ? $this->charge($patient, null, $service, $patient, 1, $by) : null;
    }

    /**
     * Consultation fee: the clinic's own fee, else the general one.
     */
    public function chargeVisit(Visit $visit, ?User $by): ?BillItem
    {
        $service = Service::active()->where('clinic_id', $visit->clinic_id)->first()
            ?? Service::active()->where('code', Service::CONSULTATION)->first();

        return $service ? $this->charge($visit->patient, $visit, $service, $visit, 1, $by) : null;
    }

    /**
     * Void the charges raised by something that was cancelled.
     */
    public function voidFor(Model $source, ?User $by, string $reason): void
    {
        BillItem::active()->whereMorphedTo('source', $source)->get()
            ->each(fn (BillItem $item) => $this->markVoid($item, $by, $reason));
    }

    public function voidItem(BillItem $item, User $by, string $reason): void
    {
        if ($item->voided_at) {
            return;
        }
        if ($item->paid_amount > 0) {
            throw ValidationException::withMessages(['item' => 'This charge has payments against it. Reverse the payment first.']);
        }

        $this->markVoid($item, $by, $reason);
        Audit::log('bill_item_voided', "Voided \"{$item->description}\" on {$item->bill->bill_number}: {$reason}", $item->bill);
    }

    public function discount(BillItem $item, float $amount, string $reason, User $by): void
    {
        $max = round($item->patient_amount - $item->paid_amount, 2);
        if ($amount < 0 || $amount > $max) {
            throw ValidationException::withMessages(['discount_amount' => 'The discount cannot exceed the unpaid patient amount ('.money($max).').']);
        }

        $item->forceFill(['discount_amount' => $amount, 'discount_reason' => $reason, 'discounted_by' => $by->id])->save();
        Audit::log('bill_discount', "Discount of ".money($amount)." on \"{$item->description}\" ({$item->bill->bill_number}): {$reason}", $item->bill);
    }

    /**
     * Take a payment, allocating it to the chosen items (oldest first).
     *
     * @param  list<int>  $itemIds  empty = all outstanding items
     */
    public function pay(Patient $patient, array $itemIds, float $amount, string $method, ?string $reference, ?User $by): Payment
    {
        return DB::transaction(function () use ($patient, $itemIds, $amount, $method, $reference, $by) {
            $items = BillItem::unpaid()
                ->whereHas('bill', fn ($q) => $q->where('patient_id', $patient->id))
                ->when($itemIds, fn ($q) => $q->whereIn('id', $itemIds))
                ->orderBy('id')->lockForUpdate()->get();

            $outstanding = round($items->sum(fn (BillItem $i) => $i->outstanding()), 2);
            if ($items->isEmpty() || $amount <= 0) {
                throw ValidationException::withMessages(['amount' => 'Nothing to pay.']);
            }
            if ($amount > $outstanding) {
                throw ValidationException::withMessages(['amount' => 'The amount is more than the '.money($outstanding).' owed on the selected items.']);
            }

            $payment = new Payment(['amount' => $amount, 'method' => $method, 'reference' => $reference]);
            $payment->receipt_number = ConsultationService::number('RCT');
            $payment->patient_id = $patient->id;
            $payment->received_by = $by?->id; // null = online payment
            $payment->save();

            $left = $amount;
            foreach ($items as $item) {
                if ($left <= 0) {
                    break;
                }
                $portion = round(min($left, $item->outstanding()), 2);
                $item->increment('paid_amount', $portion);
                $payment->allocations()->create(['bill_item_id' => $item->id, 'amount' => $portion]);
                $left = round($left - $portion, 2);
            }

            return $payment;
        });
    }

    /**
     * Money received in advance, held as credit until used.
     */
    public function deposit(Patient $patient, float $amount, string $method, ?string $reference, ?User $by): Payment
    {
        $payment = new Payment(['amount' => $amount, 'method' => $method, 'reference' => $reference]);
        $payment->receipt_number = ConsultationService::number('RCT');
        $payment->patient_id = $patient->id;
        $payment->received_by = $by?->id;
        $payment->save();

        Audit::log('deposit_received', 'Deposit of '.money($amount)." received ({$payment->receipt_number})", $patient);

        return $payment;
    }

    /**
     * Unused deposit money (payments not yet allocated to charges).
     */
    public function depositBalance(Patient $patient): float
    {
        return round((float) Payment::where('patient_id', $patient->id)->whereNull('voided_at')
            ->withSum('allocations', 'amount')->get()
            ->sum(fn (Payment $p) => $p->amount - (float) $p->allocations_sum_amount), 2);
    }

    /**
     * Settle charges from the patient's deposit, oldest deposit first.
     *
     * @param  list<int>  $itemIds
     */
    public function payFromDeposit(Patient $patient, array $itemIds, float $amount, User $by): float
    {
        return DB::transaction(function () use ($patient, $itemIds, $amount, $by) {
            if ($amount > $this->depositBalance($patient)) {
                throw ValidationException::withMessages(['amount' => 'Only '.money($this->depositBalance($patient)).' is available on deposit.']);
            }

            $items = BillItem::unpaid()->whereHas('bill', fn ($q) => $q->where('patient_id', $patient->id))
                ->when($itemIds, fn ($q) => $q->whereIn('id', $itemIds))->orderBy('id')->lockForUpdate()->get();
            if ($amount > round($items->sum(fn (BillItem $i) => $i->outstanding()), 2)) {
                throw ValidationException::withMessages(['amount' => 'The amount is more than is owed on the selected items.']);
            }

            $deposits = Payment::where('patient_id', $patient->id)->whereNull('voided_at')
                ->withSum('allocations', 'amount')->orderBy('id')->lockForUpdate()->get()
                ->map(fn (Payment $p) => [$p, round($p->amount - (float) $p->allocations_sum_amount, 2)])
                ->filter(fn ($row) => $row[1] > 0)->values();

            $left = $amount;
            foreach ($items as $item) {
                $need = min($left, $item->outstanding());
                while ($need > 0 && $deposits->isNotEmpty()) {
                    [$deposit, $available] = $deposits->first();
                    $take = round(min($need, $available), 2);
                    $deposit->allocations()->create(['bill_item_id' => $item->id, 'amount' => $take]);
                    $item->increment('paid_amount', $take);
                    $need = round($need - $take, 2);
                    $left = round($left - $take, 2);
                    $available = round($available - $take, 2);
                    if ($available > 0) {
                        $deposits[0] = [$deposit, $available];
                    } else {
                        $deposits->shift();
                    }
                }
                if ($left <= 0) {
                    break;
                }
            }

            Audit::log('deposit_applied', money($amount - $left).' applied from deposit', $patient);

            return round($amount - $left, 2);
        });
    }

    public function voidPayment(Payment $payment, string $reason, User $by): void
    {
        if ($payment->isVoided()) {
            throw ValidationException::withMessages(['payment' => 'This payment is already reversed.']);
        }

        DB::transaction(function () use ($payment, $reason, $by) {
            foreach ($payment->allocations()->with('item')->get() as $allocation) {
                $allocation->item->decrement('paid_amount', $allocation->amount);
            }
            $payment->forceFill(['voided_at' => now(), 'voided_by' => $by->id, 'void_reason' => $reason])->save();
        });

        Audit::log('payment_reversed', "Receipt {$payment->receipt_number} (".money($payment->amount).") reversed: {$reason}", $payment);
    }

    /**
     * What the patient still owes for the charges raised by these sources.
     */
    public function outstandingFor(Collection $sources): float
    {
        return round($sources->sum(fn (Model $source) => BillItem::unpaid()->whereMorphedTo('source', $source)->get()
            ->sum(fn (BillItem $i) => $i->outstanding())), 2);
    }

    /**
     * Enforce "pay before service" for self-pay shares, when switched on.
     */
    public function assertPaid(Collection $sources, string $service): void
    {
        if (! setting('bill_before_service')) {
            return;
        }

        $due = $this->outstandingFor($sources);
        if ($due > 0) {
            throw ValidationException::withMessages([
                'payment' => "Payment of ".money($due)." is required at the cashier before {$service}.",
            ]);
        }
    }

    protected function markVoid(BillItem $item, ?User $by, string $reason): void
    {
        $item->forceFill(['voided_at' => now(), 'voided_by' => $by?->id, 'void_reason' => $reason])->save();
    }
}
