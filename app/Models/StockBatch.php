<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockBatch extends Model
{
    protected $fillable = ['drug_id', 'batch_number', 'expiry_date', 'quantity_received', 'quantity_on_hand', 'unit_cost'];

    protected function casts(): array
    {
        return ['expiry_date' => 'date', 'unit_cost' => 'decimal:2'];
    }

    public function drug(): BelongsTo
    {
        return $this->belongsTo(Drug::class);
    }

    public function receipt(): BelongsTo
    {
        return $this->belongsTo(StockReceipt::class, 'stock_receipt_id');
    }

    /**
     * In stock and not expired, earliest expiry first (FEFO).
     */
    public function scopeUsable(Builder $query): void
    {
        $query->where('quantity_on_hand', '>', 0)
            ->whereDate('expiry_date', '>=', today())
            ->orderBy('expiry_date')
            ->orderBy('id');
    }

    public function isExpired(): bool
    {
        return $this->expiry_date->lt(today());
    }

    public function expiresSoon(): bool
    {
        return ! $this->isExpired() && $this->expiry_date->lte(today()->addDays(Drug::EXPIRY_WARNING_DAYS));
    }
}
