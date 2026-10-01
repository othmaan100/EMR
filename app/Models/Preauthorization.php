<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Preauthorization extends Model
{
    public const STATUSES = [
        'requested' => ['label' => 'Awaiting HMO', 'color' => 'warning'],
        'approved' => ['label' => 'Approved', 'color' => 'success'],
        'declined' => ['label' => 'Declined', 'color' => 'danger'],
    ];

    protected $fillable = ['patient_id', 'insurance_provider_id', 'bill_id', 'services', 'diagnosis', 'amount_requested', 'notes'];

    protected function casts(): array
    {
        return ['valid_until' => 'date', 'decided_at' => 'datetime', 'amount_requested' => 'float', 'amount_approved' => 'float'];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function insuranceProvider(): BelongsTo
    {
        return $this->belongsTo(InsuranceProvider::class);
    }

    public function bill(): BelongsTo
    {
        return $this->belongsTo(Bill::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    public function isExpired(): bool
    {
        return $this->valid_until !== null && $this->valid_until->isPast() && ! $this->valid_until->isToday();
    }
}
