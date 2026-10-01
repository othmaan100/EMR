<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RequisitionItem extends Model
{
    protected $fillable = ['store_item_id', 'quantity_requested'];

    protected function casts(): array
    {
        return ['quantity_requested' => 'integer', 'quantity_issued' => 'integer'];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(StoreItem::class, 'store_item_id');
    }

    public function outstanding(): int
    {
        return max(0, $this->quantity_requested - $this->quantity_issued);
    }
}
