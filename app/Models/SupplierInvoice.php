<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierInvoice extends Model
{
    protected $fillable = ['supplier_id', 'purchase_order_id', 'invoice_number', 'invoice_date', 'due_date', 'amount', 'notes'];

    protected function casts(): array
    {
        return ['invoice_date' => 'date', 'due_date' => 'date', 'paid_on' => 'date', 'amount' => 'float'];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function isOverdue(): bool
    {
        return $this->status === 'unpaid' && $this->due_date !== null && $this->due_date->lt(today());
    }
}
