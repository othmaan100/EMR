<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Appointment extends Model
{
    use Auditable, HasFactory;

    public const STATUSES = [
        'requested' => ['label' => 'Requested online', 'color' => 'warning'],
        'scheduled' => ['label' => 'Scheduled', 'color' => 'primary'],
        'checked_in' => ['label' => 'Checked in', 'color' => 'info'],
        'completed' => ['label' => 'Completed', 'color' => 'success'],
        'cancelled' => ['label' => 'Cancelled', 'color' => 'dark'],
        'no_show' => ['label' => 'No-show', 'color' => 'secondary'],
    ];

    public const TYPES = [
        'new' => 'New consultation',
        'follow_up' => 'Follow-up',
        'review' => 'Result review',
        'procedure' => 'Procedure',
    ];

    protected $fillable = ['patient_id', 'clinic_id', 'doctor_id', 'scheduled_at', 'type', 'reason', 'notes'];

    protected function casts(): array
    {
        return ['scheduled_at' => 'datetime'];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'doctor_id');
    }

    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class);
    }

    public function bookedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'booked_by');
    }

    public function isScheduled(): bool
    {
        return $this->status === 'scheduled';
    }

    /**
     * Still "scheduled" but the day has passed.
     */
    public function isMissed(): bool
    {
        return $this->isScheduled() && $this->scheduled_at->lt(today());
    }

    public function statusLabel(): string
    {
        return $this->isMissed() ? 'Missed' : (self::STATUSES[$this->status]['label'] ?? $this->status);
    }

    public function statusColor(): string
    {
        return $this->isMissed() ? 'danger' : (self::STATUSES[$this->status]['color'] ?? 'secondary');
    }
}
