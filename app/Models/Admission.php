<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Admission extends Model
{
    use Auditable;

    public const DISCHARGE_TYPES = [
        'home' => 'Discharged home',
        'referred' => 'Referred to another facility',
        'dama' => 'Discharged against medical advice',
        'died' => 'Died',
        'absconded' => 'Absconded',
    ];

    protected $fillable = ['reason', 'final_diagnosis', 'discharge_summary', 'discharge_medications', 'follow_up', 'discharge_type'];

    protected function casts(): array
    {
        return ['admitted_at' => 'datetime', 'discharged_at' => 'datetime', 'bed_charged_until' => 'date'];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class);
    }

    public function ward(): BelongsTo
    {
        return $this->belongsTo(Ward::class);
    }

    public function bed(): BelongsTo
    {
        return $this->belongsTo(Bed::class);
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'doctor_id');
    }

    public function admittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admitted_by');
    }

    public function dischargedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'discharged_by');
    }

    public function notes(): HasMany
    {
        return $this->hasMany(AdmissionNote::class)->latest();
    }

    public function movements(): HasMany
    {
        return $this->hasMany(BedMovement::class)->latest('id');
    }

    public function prescriptions(): HasMany
    {
        return $this->hasMany(Prescription::class);
    }

    public function labOrders(): HasMany
    {
        return $this->hasMany(LabOrder::class);
    }

    public function imagingOrders(): HasMany
    {
        return $this->hasMany(ImagingOrder::class);
    }

    public function administrations(): HasMany
    {
        return $this->hasMany(MedicationAdministration::class);
    }

    public function bill(): HasOne
    {
        return $this->hasOne(Bill::class);
    }

    public function scopeCurrent(Builder $query): void
    {
        $query->where('status', 'admitted');
    }

    public function isCurrent(): bool
    {
        return $this->status === 'admitted';
    }

    /**
     * Calendar days in hospital, counting both the admission and the
     * discharge (or current) day — the same days that bed charges cover.
     */
    public function lengthOfStay(): int
    {
        $end = ($this->discharged_at ?? now())->copy()->startOfDay();

        return (int) $this->admitted_at->copy()->startOfDay()->diffInDays($end) + 1;
    }
}
