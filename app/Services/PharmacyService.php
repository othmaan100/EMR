<?php

namespace App\Services;

use App\Models\BillItem;
use App\Models\Drug;
use App\Models\Prescription;
use App\Models\PrescriptionItem;
use App\Models\StockBatch;
use App\Models\StockMovement;
use App\Models\StockReceipt;
use App\Models\User;
use App\Support\AllergyChecker;
use App\Support\Audit;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PharmacyService
{
    public function __construct(protected BillingService $billing) {}

    /**
     * Receive a delivery: one receipt, one batch per line.
     *
     * @param  array{supplier_id: ?int, invoice_number: ?string, received_on: string, notes: ?string}  $header
     * @param  list<array{drug_id: int, batch_number: string, expiry_date: string, quantity: int, unit_cost: ?float}>  $lines
     */
    public function receive(array $header, array $lines, User $by): StockReceipt
    {
        return DB::transaction(function () use ($header, $lines, $by) {
            $receipt = new StockReceipt($header);
            $receipt->receipt_number = ConsultationService::number('GRN');
            $receipt->received_by = $by->id;
            $receipt->save();

            foreach ($lines as $line) {
                $batch = $receipt->batches()->create([
                    'drug_id' => $line['drug_id'],
                    'batch_number' => $line['batch_number'],
                    'expiry_date' => $line['expiry_date'],
                    'quantity_received' => $line['quantity'],
                    'quantity_on_hand' => $line['quantity'],
                    'unit_cost' => $line['unit_cost'] ?? null,
                ]);
                $this->record($batch, 'receipt', $line['quantity'], $by, $receipt, "Received on {$receipt->receipt_number}");
            }

            return $receipt;
        });
    }

    /**
     * Add or remove stock from one batch with a reason.
     */
    public function adjust(StockBatch $batch, int $change, string $type, string $reason, User $by): void
    {
        DB::transaction(function () use ($batch, $change, $type, $reason, $by) {
            $batch = StockBatch::lockForUpdate()->findOrFail($batch->id);

            if ($batch->quantity_on_hand + $change < 0) {
                throw ValidationException::withMessages(['quantity' => "Batch {$batch->batch_number} only has {$batch->quantity_on_hand} on hand."]);
            }

            $batch->increment('quantity_on_hand', $change);
            $this->record($batch, $type, $change, $by, null, $reason);
        });

        Audit::log('stock_adjusted', "Stock {$type} of {$change} on {$batch->drug->label} batch {$batch->batch_number}: {$reason}", $batch->drug);
    }

    /**
     * Dispense a prescription.
     *
     * @param  array<int, array{quantity?: ?int, unavailable?: ?string}>  $lines  item_id => input
     */
    public function dispense(Prescription $prescription, array $lines, User $by, bool $allergyConfirmed): int
    {
        if (! in_array($prescription->status, ['pending', 'partially_dispensed'], true)) {
            throw ValidationException::withMessages(['status' => "Prescription {$prescription->prescription_number} is {$prescription->statusLabel()}."]);
        }

        $items = $prescription->items()->with('drug')->get()->keyBy('id');
        $patient = $prescription->patient;

        // Pharmacist double-check of allergies.
        $conflicts = $items->filter(fn (PrescriptionItem $i) => ! empty($lines[$i->id]['quantity'])
            && AllergyChecker::conflicts($patient, $i->drug_name));
        if ($conflicts->isNotEmpty() && ! $allergyConfirmed) {
            throw ValidationException::withMessages([
                'allergy' => 'Allergy alert on '.$conflicts->pluck('drug_name')->implode(', ').'. Confirm you have checked with the prescriber.',
            ]);
        }

        $payFirst = (bool) setting('pharmacy_pay_first');

        return DB::transaction(function () use ($prescription, $items, $lines, $by, $payFirst) {
            $dispensedLines = 0;
            $errors = [];

            foreach ($lines as $itemId => $input) {
                $item = $items->get($itemId);
                if (! $item || $item->isComplete()) {
                    continue;
                }

                if (! empty($input['unavailable'])) {
                    $this->markUnavailable($item, $input['unavailable'], $by);
                    continue;
                }

                $qty = (int) ($input['quantity'] ?? 0);
                if ($qty <= 0) {
                    continue;
                }
                if (! $item->drug_id) {
                    $errors["lines.$itemId.quantity"] = "{$item->drug_name} is not a formulary item; mark it as not available instead.";
                    continue;
                }
                if ($item->remaining() !== null && $qty > $item->remaining()) {
                    $errors["lines.$itemId.quantity"] = "{$item->drug_name}: only {$item->remaining()} still to dispense.";
                    continue;
                }

                // Quantity already priced (and, under pay-first, paid for) is not charged again.
                $priced = $item->pricedQuantity();
                if ($payFirst) {
                    if ($priced === 0) {
                        continue; // not priced yet — the "Price" button handles these lines
                    }
                    if ($qty > $priced) {
                        $errors["lines.$itemId.quantity"] = "{$item->drug_name}: only {$priced} priced for payment.";
                        continue;
                    }
                    $due = $this->billing->outstandingFor(collect([$item]));
                    if ($due > 0) {
                        $errors["lines.$itemId.quantity"] = "{$item->drug_name}: payment of ".money($due).' is still due at the cashier.';
                        continue;
                    }
                }

                $shortfall = $this->takeFromStock($item->drug, $qty, $by, $item);
                if ($shortfall > 0) {
                    $errors["lines.$itemId.quantity"] = "{$item->drug_name}: not enough usable stock (short by {$shortfall}).";
                    continue;
                }

                $toCharge = max(0, $qty - $priced);
                if ($toCharge > 0) {
                    $this->billing->charge($prescription->patient, $prescription->admission ?? $prescription->visit, $item->drug, $item, $toCharge, $by,
                        "{$item->drug_name} × {$toCharge}");
                }
                $item->forceFill([
                    'quantity_dispensed' => $item->quantity_dispensed + $qty,
                    'billed_quantity' => $item->billed_quantity + $toCharge,
                ])->save();
                $dispensedLines++;
            }

            if ($errors) {
                // Undo every stock movement made in this attempt.
                throw ValidationException::withMessages($errors);
            }

            $prescription->forceFill(['dispensed_at' => now(), 'dispensed_by' => $by->id])->save();
            $prescription->refreshStatus();

            return $dispensedLines;
        });
    }

    /**
     * Pharmacy pay-first: charge the quantities to be given so the patient
     * can pay at the cashier; dispensing then only releases paid quantities.
     *
     * @param  array<int, array{quantity?: ?int, unavailable?: ?string}>  $lines  item_id => input
     */
    public function price(Prescription $prescription, array $lines, User $by): int
    {
        if (! in_array($prescription->status, ['pending', 'partially_dispensed'], true)) {
            throw ValidationException::withMessages(['status' => "Prescription {$prescription->prescription_number} is {$prescription->statusLabel()}."]);
        }

        $items = $prescription->items()->with('drug')->get()->keyBy('id');

        return DB::transaction(function () use ($prescription, $items, $lines, $by) {
            $priced = 0;
            $errors = [];

            foreach ($lines as $itemId => $input) {
                $item = $items->get($itemId);
                if (! $item || $item->isComplete()) {
                    continue;
                }

                if (! empty($input['unavailable'])) {
                    $this->markUnavailable($item, $input['unavailable'], $by);
                    continue;
                }

                $qty = (int) ($input['quantity'] ?? 0);
                if ($qty <= 0 || $item->pricedQuantity() > 0) {
                    continue; // nothing entered, or already waiting at the cashier
                }
                if (! $item->drug_id) {
                    $errors["lines.$itemId.quantity"] = "{$item->drug_name} is not a formulary item; mark it as not available instead.";
                    continue;
                }
                if ($item->remaining() !== null && $qty > $item->remaining()) {
                    $errors["lines.$itemId.quantity"] = "{$item->drug_name}: only {$item->remaining()} still to dispense.";
                    continue;
                }
                if ($qty > $item->drug->usableStock()) {
                    $errors["lines.$itemId.quantity"] = "{$item->drug_name}: only {$item->drug->usableStock()} in usable stock.";
                    continue;
                }

                $this->billing->charge($prescription->patient, $prescription->admission ?? $prescription->visit, $item->drug, $item, $qty, $by,
                    "{$item->drug_name} × {$qty}");
                $item->forceFill(['billed_quantity' => $item->billed_quantity + $qty])->save();
                $priced++;
            }

            if ($errors) {
                throw ValidationException::withMessages($errors);
            }

            return $priced;
        });
    }

    /**
     * Mark an item not available and cancel any unpaid charge priced for it.
     */
    protected function markUnavailable(PrescriptionItem $item, string $reason, User $by): void
    {
        // Only charges for quantity never given can be cancelled (newest first);
        // paid ones stay for the cashier to refund.
        BillItem::active()->whereMorphedTo('source', $item)->where('paid_amount', 0)->latest('id')->get()
            ->each(function (BillItem $charge) use ($item, $by, $reason) {
                if ($charge->quantity > $item->pricedQuantity()) {
                    return false;
                }
                $this->billing->voidItem($charge, $by, "Not dispensed: {$reason}");
                $item->billed_quantity -= (int) $charge->quantity;
            });

        $item->not_dispensed_reason = $reason;
        $item->save();
    }

    /**
     * First-expiry-first-out allocation across batches.
     * Returns the quantity that could NOT be supplied (0 = success).
     */
    protected function takeFromStock(Drug $drug, int $qty, User $by, PrescriptionItem $item): int
    {
        $batches = StockBatch::where('drug_id', $drug->id)->usable()->lockForUpdate()->get();

        if ($batches->sum('quantity_on_hand') < $qty) {
            return $qty - $batches->sum('quantity_on_hand');
        }

        foreach ($batches as $batch) {
            if ($qty === 0) {
                break;
            }
            $take = min($qty, $batch->quantity_on_hand);
            $batch->decrement('quantity_on_hand', $take);
            $this->record($batch, 'dispense', -$take, $by, $item->prescription, "Dispensed {$item->drug_name}");
            $qty -= $take;
        }

        return 0;
    }

    protected function record(StockBatch $batch, string $type, int $quantity, User $by, $reference = null, ?string $reason = null): void
    {
        $movement = new StockMovement([
            'drug_id' => $batch->drug_id,
            'stock_batch_id' => $batch->id,
            'type' => $type,
            'quantity' => $quantity,
            'reason' => $reason,
            'user_id' => $by->id,
        ]);
        $movement->reference()->associate($reference);
        $movement->save();
    }
}
