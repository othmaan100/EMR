<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Bill extends Model
{
    public const CLAIM_STATUSES = [
        'none' => ['label' => 'No claim', 'color' => 'light'],
        'pending' => ['label' => 'To submit', 'color' => 'warning'],
        'submitted' => ['label' => 'Submitted', 'color' => 'info'],
        'paid' => ['label' => 'Paid', 'color' => 'success'],
        'part_paid' => ['label' => 'Part-paid', 'color' => 'primary'],
        'rejected' => ['label' => 'Rejected', 'color' => 'danger'],
    ];

    protected function casts(): array
    {
        return ['claim_submitted_at' => 'datetime', 'claim_paid_at' => 'datetime', 'claim_transferred_at' => 'datetime',
            'claim_amount' => 'float', 'claim_amount_paid' => 'float'];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class);
    }

    public function admission(): BelongsTo
    {
        return $this->belongsTo(Admission::class);
    }

    public function insuranceProvider(): BelongsTo
    {
        return $this->belongsTo(InsuranceProvider::class);
    }

    public function claimBatch(): BelongsTo
    {
        return $this->belongsTo(ClaimBatch::class);
    }

    /**
     * Diagnoses recorded on this bill's visit (for claim forms).
     *
     * @return \Illuminate\Support\Collection<int, Diagnosis>
     */
    public function diagnoses(): \Illuminate\Support\Collection
    {
        return $this->visit?->consultation?->diagnoses()->orderByDesc('is_primary')->get() ?? collect();
    }

    /**
     * The insurer's share that was claimed but not paid.
     */
    public function claimShortfall(): float
    {
        return in_array($this->claim_status, ['rejected', 'part_paid'], true) && $this->claim_amount !== null
            ? max(0, round($this->claim_amount - $this->claim_amount_paid, 2)) : 0.0;
    }

    public function items(): HasMany
    {
        return $this->hasMany(BillItem::class)->orderBy('id');
    }

    public function activeItems(): HasMany
    {
        return $this->items()->whereNull('voided_at');
    }

    /**
     * @return array{amount: float, insurance: float, patient: float, discount: float, paid: float, balance: float}
     */
    public function totals(): array
    {
        $items = $this->relationLoaded('items') ? $this->items->whereNull('voided_at') : $this->activeItems()->get();

        $t = [
            'amount' => (float) $items->sum('amount'),
            'insurance' => (float) $items->sum('insurance_amount'),
            'patient' => (float) $items->sum('patient_amount'),
            'discount' => (float) $items->sum('discount_amount'),
            'paid' => (float) $items->sum('paid_amount'),
        ];
        $t['balance'] = round($t['patient'] - $t['discount'] - $t['paid'], 2);

        return $t;
    }
}
