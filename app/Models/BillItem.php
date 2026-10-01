<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class BillItem extends Model
{
    protected $fillable = ['description', 'quantity', 'unit_price', 'amount', 'insurance_amount', 'patient_amount'];

    protected function casts(): array
    {
        return [
            'quantity' => 'float',
            'unit_price' => 'float',
            'amount' => 'float',
            'insurance_amount' => 'float',
            'patient_amount' => 'float',
            'discount_amount' => 'float',
            'paid_amount' => 'float',
            'voided_at' => 'datetime',
        ];
    }

    public function bill(): BelongsTo
    {
        return $this->belongsTo(Bill::class);
    }

    public function billable(): MorphTo
    {
        return $this->morphTo();
    }

    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * What the patient still owes on this line.
     */
    public function outstanding(): float
    {
        return $this->voided_at ? 0.0 : round($this->patient_amount - $this->discount_amount - $this->paid_amount, 2);
    }

    public function isSettled(): bool
    {
        return $this->outstanding() <= 0;
    }

    public function scopeActive(Builder $query): void
    {
        $query->whereNull('voided_at');
    }

    /**
     * Active lines with something left to pay.
     */
    public function scopeUnpaid(Builder $query): void
    {
        $query->whereNull('voided_at')->whereRaw('patient_amount - discount_amount - paid_amount > 0.004');
    }
}
