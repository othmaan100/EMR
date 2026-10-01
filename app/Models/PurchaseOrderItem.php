<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class PurchaseOrderItem extends Model
{
    protected $fillable = ['description', 'quantity', 'unit_price'];

    protected function casts(): array
    {
        return ['quantity' => 'integer', 'quantity_received' => 'integer', 'unit_price' => 'float'];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id');
    }

    public function item(): MorphTo
    {
        return $this->morphTo();
    }

    public function isDrug(): bool
    {
        return $this->item_type === (new Drug)->getMorphClass();
    }

    public function outstanding(): int
    {
        return max(0, $this->quantity - $this->quantity_received);
    }

    public function lineTotal(): float
    {
        return round($this->quantity * $this->unit_price, 2);
    }
}
