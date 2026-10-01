<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrescriptionItem extends Model
{
    protected $fillable = ['drug_id', 'drug_name', 'dose', 'route', 'frequency', 'duration', 'quantity', 'instructions', 'allergy_override'];

    protected function casts(): array
    {
        return ['allergy_override' => 'boolean', 'quantity_dispensed' => 'integer', 'billed_quantity' => 'integer', 'quantity' => 'integer'];
    }

    /**
     * Quantity still owed, or null when the prescriber gave no quantity.
     */
    /**
     * Pharmacy pay-first: quantity priced for the cashier but not yet given.
     */
    public function pricedQuantity(): int
    {
        return max(0, $this->billed_quantity - $this->quantity_dispensed);
    }

    public function remaining(): ?int
    {
        return $this->quantity === null ? null : max(0, $this->quantity - $this->quantity_dispensed);
    }

    /**
     * Fully dispensed, or marked as not available.
     */
    public function isComplete(): bool
    {
        if ($this->not_dispensed_reason !== null) {
            return true;
        }

        return $this->quantity === null ? $this->quantity_dispensed > 0 : $this->quantity_dispensed >= $this->quantity;
    }

    public function prescription(): BelongsTo
    {
        return $this->belongsTo(Prescription::class);
    }

    public function drug(): BelongsTo
    {
        return $this->belongsTo(Drug::class);
    }

    /**
     * e.g. "500mg Oral TDS × 5 days"
     */
    public function directions(): string
    {
        return "{$this->dose} {$this->route} {$this->frequency} × {$this->duration}";
    }
}
