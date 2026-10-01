<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClaimBatch extends Model
{
    public const STATUSES = [
        'draft' => ['label' => 'Draft', 'color' => 'secondary'],
        'submitted' => ['label' => 'Submitted', 'color' => 'info'],
        'reconciled' => ['label' => 'Reconciled', 'color' => 'success'],
    ];

    protected $fillable = ['insurance_provider_id', 'period_from', 'period_to', 'notes'];

    protected function casts(): array
    {
        return ['period_from' => 'date', 'period_to' => 'date', 'paid_on' => 'date', 'submitted_at' => 'datetime',
            'amount_claimed' => 'float', 'amount_paid' => 'float'];
    }

    public function insuranceProvider(): BelongsTo
    {
        return $this->belongsTo(InsuranceProvider::class);
    }

    public function bills(): HasMany
    {
        return $this->hasMany(Bill::class)->orderBy('id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status]['label'] ?? $this->status;
    }

    public function statusColor(): string
    {
        return self::STATUSES[$this->status]['color'] ?? 'secondary';
    }

    /**
     * Claimed but not yet paid or decided.
     */
    public function outstanding(): float
    {
        return round($this->bills->where('claim_status', 'submitted')->sum('claim_amount'), 2);
    }
}
