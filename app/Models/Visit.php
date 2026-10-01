<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Visit extends Model
{
    use Auditable, HasFactory;

    public const WAITING_TRIAGE = 'waiting_triage';

    public const WAITING_DOCTOR = 'waiting_doctor';

    public const IN_CONSULTATION = 'in_consultation';

    public const COMPLETED = 'completed';

    public const LEFT = 'left';

    public const CANCELLED = 'cancelled';

    public const STATUSES = [
        self::WAITING_TRIAGE => ['label' => 'Waiting for triage', 'color' => 'warning', 'icon' => 'bi-hourglass-split'],
        self::WAITING_DOCTOR => ['label' => 'Waiting for doctor', 'color' => 'info', 'icon' => 'bi-person-lines-fill'],
        self::IN_CONSULTATION => ['label' => 'In consultation', 'color' => 'primary', 'icon' => 'bi-clipboard2-pulse'],
        self::COMPLETED => ['label' => 'Completed', 'color' => 'success', 'icon' => 'bi-check-circle'],
        self::LEFT => ['label' => 'Left without being seen', 'color' => 'secondary', 'icon' => 'bi-door-open'],
        self::CANCELLED => ['label' => 'Cancelled', 'color' => 'dark', 'icon' => 'bi-x-circle'],
    ];

    public const OPEN_STATUSES = [self::WAITING_TRIAGE, self::WAITING_DOCTOR, self::IN_CONSULTATION];

    /**
     * Allowed queue moves: from => [to, ...].
     */
    public const TRANSITIONS = [
        self::WAITING_TRIAGE => [self::WAITING_DOCTOR, self::LEFT, self::CANCELLED],
        self::WAITING_DOCTOR => [self::IN_CONSULTATION, self::WAITING_TRIAGE, self::LEFT, self::CANCELLED],
        self::IN_CONSULTATION => [self::COMPLETED, self::WAITING_DOCTOR],
    ];

    public const PRIORITIES = [
        'emergency' => ['label' => 'Emergency', 'color' => 'danger', 'rank' => 1],
        'urgent' => ['label' => 'Urgent', 'color' => 'warning', 'rank' => 2],
        'normal' => ['label' => 'Normal', 'color' => 'secondary', 'rank' => 3],
    ];

    public const TYPES = [
        'outpatient' => 'Outpatient',
        'emergency' => 'Emergency',
        'follow_up' => 'Follow-up',
        'antenatal' => 'Antenatal',
        'procedure' => 'Procedure',
    ];

    protected $fillable = [
        'patient_id', 'clinic_id', 'doctor_id', 'visit_type', 'priority', 'status', 'complaint', 'closing_note',
    ];

    protected function casts(): array
    {
        return [
            'checked_in_at' => 'datetime',
            'triaged_at' => 'datetime',
            'consultation_started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
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

    public function checkedInBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_in_by');
    }

    public function appointment(): HasOne
    {
        return $this->hasOne(Appointment::class);
    }

    public function consultation(): HasOne
    {
        return $this->hasOne(Consultation::class);
    }

    public function vitalSigns(): HasMany
    {
        return $this->hasMany(VitalSign::class);
    }

    public function latestVitals(): HasOne
    {
        return $this->hasOne(VitalSign::class)->whereNull('voided_at')->latestOfMany('recorded_at');
    }

    public function scopeOpen(Builder $query): void
    {
        $query->whereIn('status', self::OPEN_STATUSES);
    }

    /**
     * Emergencies first, then urgent, then in order of arrival.
     */
    public function scopeQueueOrder(Builder $query): void
    {
        $query->orderByRaw("CASE priority WHEN 'emergency' THEN 1 WHEN 'urgent' THEN 2 ELSE 3 END")
            ->orderBy('checked_in_at');
    }

    public function isOpen(): bool
    {
        return in_array($this->status, self::OPEN_STATUSES, true);
    }

    public function canMoveTo(string $status): bool
    {
        return in_array($status, self::TRANSITIONS[$this->status] ?? [], true);
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
     * Minutes since the patient entered their current stage.
     */
    public function minutesInStage(): int
    {
        $since = match ($this->status) {
            self::WAITING_DOCTOR => $this->triaged_at,
            self::IN_CONSULTATION => $this->consultation_started_at,
            default => null,
        } ?? $this->checked_in_at;

        return (int) $since->diffInMinutes(now());
    }
}
