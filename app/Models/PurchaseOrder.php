<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseOrder extends Model
{
    public const STATUSES = [
        'draft' => ['label' => 'Draft — needs approval', 'color' => 'secondary'],
        'approved' => ['label' => 'Approved — awaiting delivery', 'color' => 'primary'],
        'partially_received' => ['label' => 'Partly received', 'color' => 'info'],
        'received' => ['label' => 'Received', 'color' => 'success'],
        'cancelled' => ['label' => 'Cancelled', 'color' => 'dark'],
    ];

    protected $fillable = ['supplier_id', 'order_date', 'expected_date', 'notes'];

    protected function casts(): array
    {
        return ['order_date' => 'date', 'expected_date' => 'date', 'approved_at' => 'datetime', 'total' => 'float'];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class)->orderBy('id');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(SupplierInvoice::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status]['label'] ?? $this->status;
    }

    public function statusColor(): string
    {
        return self::STATUSES[$this->status]['color'] ?? 'secondary';
    }

    public function canReceive(): bool
    {
        return in_array($this->status, ['approved', 'partially_received'], true);
    }

    /**
     * Value of goods received so far (for matching supplier invoices).
     */
    public function receivedValue(): float
    {
        return round($this->items->sum(fn (PurchaseOrderItem $i) => $i->quantity_received * $i->unit_price), 2);
    }
}
