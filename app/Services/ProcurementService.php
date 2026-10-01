<?php

namespace App\Services;

use App\Models\Drug;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\StoreItem;
use App\Models\Supplier;
use App\Models\SupplierInvoice;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Purchase orders (store items and drugs) → approval → deliveries →
 * supplier invoices → payment.
 */
class ProcurementService
{
    public function __construct(protected StoresService $stores, protected PharmacyService $pharmacy) {}

    /**
     * @param  list<array{item: string, quantity: int, unit_price: float}>  $lines  item = "store:ID" or "drug:ID"
     */
    public function createOrder(array $header, array $lines, User $by): PurchaseOrder
    {
        return DB::transaction(function () use ($header, $lines, $by) {
            $po = new PurchaseOrder($header);
            $po->po_number = ConsultationService::number('PO');
            $po->status = 'draft';
            $po->created_by = $by->id;
            $po->save();

            foreach ($lines as $line) {
                [$kind, $id] = explode(':', $line['item']) + [null, null];
                $model = match ($kind) {
                    'store' => StoreItem::findOrFail($id),
                    'drug' => Drug::findOrFail($id),
                    default => throw ValidationException::withMessages(['lines' => 'Unknown item.']),
                };

                $item = new PurchaseOrderItem([
                    'description' => $model instanceof Drug ? $model->label : $model->label,
                    'quantity' => $line['quantity'],
                    'unit_price' => $line['unit_price'],
                ]);
                $item->item()->associate($model);
                $po->items()->save($item);
            }

            $po->forceFill(['total' => $po->items()->get()->sum(fn ($i) => $i->lineTotal())])->save();

            return $po;
        });
    }

    public function approve(PurchaseOrder $po, User $by): void
    {
        $this->assertStatus($po, ['draft'], 'approve');
        if ($po->created_by === $by->id && ! $by->isSuperAdmin()) {
            throw ValidationException::withMessages(['status' => 'A purchase order must be approved by someone other than the person who raised it.']);
        }

        $po->forceFill(['status' => 'approved', 'approved_by' => $by->id, 'approved_at' => now()])->save();
        Audit::log('purchase_order_approved', "{$po->po_number} approved: ".money($po->total)." to {$po->supplier->name}", $po);
    }

    public function cancel(PurchaseOrder $po, string $reason, User $by): void
    {
        $this->assertStatus($po, ['draft', 'approved'], 'cancel');
        $po->forceFill(['status' => 'cancelled', 'cancel_reason' => $reason])->save();
        Audit::log('purchase_order_cancelled', "{$po->po_number} cancelled: {$reason}", $po);
    }

    /**
     * Record a delivery against the order.
     *
     * @param  array<int, array{quantity?: ?int, batch_number?: ?string, expiry_date?: ?string}>  $lines  po_item_id => delivered
     */
    public function receive(PurchaseOrder $po, array $lines, string $receivedOn, ?string $deliveryNote, User $by): int
    {
        $this->assertStatus($po, ['approved', 'partially_received'], 'receive against');

        return DB::transaction(function () use ($po, $lines, $receivedOn, $deliveryNote, $by) {
            $errors = [];
            $drugLines = [];
            $received = 0;

            foreach ($po->items()->with('item')->lockForUpdate()->get() as $line) {
                $input = $lines[$line->id] ?? [];
                $qty = (int) ($input['quantity'] ?? 0);
                if ($qty <= 0) {
                    continue;
                }
                if ($qty > $line->outstanding()) {
                    $errors["lines.{$line->id}.quantity"] = "{$line->description}: only {$line->outstanding()} outstanding on this order.";
                    continue;
                }

                if ($line->isDrug()) {
                    if (blank($input['batch_number'] ?? null) || blank($input['expiry_date'] ?? null)) {
                        $errors["lines.{$line->id}.batch_number"] = "{$line->description}: batch number and expiry date are required for drugs.";
                        continue;
                    }
                    if (now()->parse($input['expiry_date'])->lte(today())) {
                        $errors["lines.{$line->id}.expiry_date"] = "{$line->description}: expired stock cannot be received.";
                        continue;
                    }
                    $drugLines[] = ['drug_id' => $line->item_id, 'batch_number' => $input['batch_number'], 'expiry_date' => $input['expiry_date'],
                        'quantity' => $qty, 'unit_cost' => $line->unit_price];
                } else {
                    $this->stores->receive($line->item, $qty, $line->unit_price, $by, $po, "Delivered against {$po->po_number}");
                }

                $line->increment('quantity_received', $qty);
                $received++;
            }

            if ($errors) {
                throw ValidationException::withMessages($errors);
            }
            if ($received === 0) {
                throw ValidationException::withMessages(['lines' => 'Enter the quantity delivered for at least one item.']);
            }

            // Drugs go into pharmacy stock as batches (FEFO dispensing uses them).
            if ($drugLines) {
                $this->pharmacy->receive([
                    'supplier_id' => $po->supplier_id,
                    'invoice_number' => $deliveryNote,
                    'received_on' => $receivedOn,
                    'notes' => "Against {$po->po_number}",
                ], $drugLines, $by);
            }

            $complete = $po->items()->get()->every(fn ($l) => $l->outstanding() === 0);
            $po->forceFill(['status' => $complete ? 'received' : 'partially_received'])->save();
            Audit::log('purchase_order_received', "Delivery recorded against {$po->po_number}".($deliveryNote ? " (note {$deliveryNote})" : ''), $po);

            return $received;
        });
    }

    /**
     * Close an order that will not be delivered in full.
     */
    public function closeShort(PurchaseOrder $po, string $reason, User $by): void
    {
        $this->assertStatus($po, ['partially_received'], 'close');
        $po->forceFill(['status' => 'received', 'cancel_reason' => "Closed short: {$reason}"])->save();
        Audit::log('purchase_order_closed', "{$po->po_number} closed short: {$reason}", $po);
    }

    // ------------------------------------------------------------------ supplier invoices

    public function recordInvoice(array $data, User $by): SupplierInvoice
    {
        if (! empty($data['purchase_order_id'])) {
            $po = PurchaseOrder::findOrFail($data['purchase_order_id']);
            if ($po->supplier_id !== (int) $data['supplier_id']) {
                throw ValidationException::withMessages(['purchase_order_id' => 'That purchase order is for a different supplier.']);
            }
        }

        $invoice = new SupplierInvoice($data);
        $invoice->status = 'unpaid';
        $invoice->recorded_by = $by->id;
        $invoice->save();

        Audit::log('supplier_invoice_recorded', "Invoice {$invoice->invoice_number} from ".Supplier::find($invoice->supplier_id)?->name.': '.money($invoice->amount), $invoice);

        return $invoice;
    }

    public function payInvoice(SupplierInvoice $invoice, string $paidOn, string $reference, User $by): void
    {
        if ($invoice->status === 'paid') {
            throw ValidationException::withMessages(['status' => 'This invoice is already paid.']);
        }
        if ($invoice->recorded_by === $by->id && ! $by->isSuperAdmin()) {
            throw ValidationException::withMessages(['status' => 'Payment must be recorded by someone other than the person who entered the invoice.']);
        }

        $invoice->forceFill(['status' => 'paid', 'paid_on' => $paidOn, 'payment_reference' => $reference, 'paid_by' => $by->id])->save();
        Audit::log('supplier_invoice_paid', "Invoice {$invoice->invoice_number} paid ".money($invoice->amount)." (ref {$reference})", $invoice);
    }

    /**
     * How the invoice compares with goods actually received on its order.
     */
    public function invoiceMismatch(SupplierInvoice $invoice): ?string
    {
        $po = $invoice->purchaseOrder;
        if (! $po) {
            return null;
        }

        $invoiced = (float) $po->invoices()->sum('amount');
        $received = $po->loadMissing('items')->receivedValue();

        return $invoiced - $received > 0.5
            ? 'Invoiced '.money($invoiced).' but only '.money($received).' of goods received on '.$po->po_number.'.'
            : null;
    }

    protected function assertStatus(PurchaseOrder $po, array $allowed, string $action): void
    {
        if (! in_array($po->status, $allowed, true)) {
            throw ValidationException::withMessages(['status' => "Cannot {$action} {$po->po_number}: it is {$po->statusLabel()}."]);
        }
    }
}
