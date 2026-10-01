<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Surgery extends Model
{
    use Auditable;

    public const STATUSES = [
        'scheduled' => ['label' => 'Scheduled', 'color' => 'primary'],
        'ready' => ['label' => 'Pre-op done', 'color' => 'info'],
        'in_theatre' => ['label' => 'In theatre', 'color' => 'warning'],
        'completed' => ['label' => 'Completed', 'color' => 'success'],
        'postponed' => ['label' => 'Postponed', 'color' => 'secondary'],
        'cancelled' => ['label' => 'Cancelled', 'color' => 'dark'],
    ];

    public const URGENCY = [
        'emergency' => ['label' => 'Emergency', 'color' => 'danger'],
        'urgent' => ['label' => 'Urgent', 'color' => 'warning'],
        'elective' => ['label' => 'Elective', 'color' => 'secondary'],
    ];

    public const ANAESTHESIA = ['GA' => 'General anaesthesia', 'Spinal' => 'Spinal', 'Epidural' => 'Epidural',
        'Regional' => 'Regional nerve block', 'Local' => 'Local', 'Sedation' => 'Sedation'];

    /** WHO Surgical Safety Checklist (2009), in order. */
    public const CHECKLIST = [
        'sign_in' => ['title' => 'Sign in', 'when' => 'Before induction of anaesthesia', 'items' => [
            'Patient has confirmed identity, site, procedure and consent',
            'Site marked (or not applicable)',
            'Anaesthesia machine and medication check complete',
            'Pulse oximeter on the patient and functioning',
            'Known allergies checked',
            'Difficult airway / aspiration risk assessed; equipment and assistance available if needed',
            'Risk of >500 ml blood loss (7 ml/kg in children) assessed; IV access and fluids planned',
        ]],
        'time_out' => ['title' => 'Time out', 'when' => 'Before skin incision', 'items' => [
            'All team members introduced by name and role',
            'Surgeon, anaesthetist and nurse confirm patient, site and procedure',
            'Antibiotic prophylaxis given within the last 60 minutes (or not applicable)',
            'Surgeon reviewed critical steps, expected duration and anticipated blood loss',
            'Anaesthetist reviewed patient-specific concerns',
            'Nursing team confirmed sterility and raised any equipment issues',
            'Essential imaging displayed (or not applicable)',
        ]],
        'sign_out' => ['title' => 'Sign out', 'when' => 'Before the patient leaves theatre', 'items' => [
            'Name of the procedure recorded',
            'Instrument, sponge and needle counts are correct (or not applicable)',
            'Specimens labelled, including patient name (or none)',
            'Equipment problems to be addressed have been noted',
            'Surgeon, anaesthetist and nurse reviewed key concerns for recovery',
        ]],
    ];

    protected $fillable = [
        'surgical_procedure_id', 'procedure_name', 'theatre_id', 'surgeon_id', 'assistant', 'anaesthetist_id', 'urgency',
        'scheduled_at', 'estimated_minutes', 'indication', 'pregnancy_id', 'admission_id',
        'consent_signed', 'fasting_confirmed', 'site_marked', 'asa_grade', 'blood_units_available', 'preop_notes',
        'anaesthesia_type', 'airway', 'anaesthesia_drugs', 'fluids', 'anaesthesia_notes',
        'findings', 'procedure_performed', 'blood_loss_ml', 'specimens', 'implants', 'drains', 'closure', 'complications', 'postop_orders',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'assessed_at' => 'datetime',
            'in_theatre_at' => 'datetime',
            'incision_at' => 'datetime',
            'out_at' => 'datetime',
            'completed_at' => 'datetime',
            'checklist' => 'array',
            'consent_signed' => 'boolean',
            'fasting_confirmed' => 'boolean',
            'site_marked' => 'boolean',
        ];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function procedure(): BelongsTo
    {
        return $this->belongsTo(SurgicalProcedure::class, 'surgical_procedure_id');
    }

    public function theatre(): BelongsTo
    {
        return $this->belongsTo(Theatre::class);
    }

    public function surgeon(): BelongsTo
    {
        return $this->belongsTo(User::class, 'surgeon_id');
    }

    public function anaesthetist(): BelongsTo
    {
        return $this->belongsTo(User::class, 'anaesthetist_id');
    }

    public function admission(): BelongsTo
    {
        return $this->belongsTo(Admission::class);
    }

    public function pregnancy(): BelongsTo
    {
        return $this->belongsTo(Pregnancy::class);
    }

    public function assessor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assessed_by');
    }

    public function observations(): HasMany
    {
        return $this->hasMany(SurgeryObservation::class)->orderBy('recorded_at');
    }

    public function scopeUpcoming(Builder $query): void
    {
        $query->whereIn('status', ['scheduled', 'ready']);
    }

    public function scopeActive(Builder $query): void
    {
        $query->whereIn('status', ['scheduled', 'ready', 'in_theatre']);
    }

    public function endsAt()
    {
        return $this->scheduled_at->copy()->addMinutes($this->estimated_minutes);
    }

    public function phaseDone(string $phase): bool
    {
        return ! empty($this->checklist[$phase]['at']);
    }

    /**
     * The next WHO checklist phase to complete, or null when all are done.
     */
    public function nextPhase(): ?string
    {
        foreach (array_keys(self::CHECKLIST) as $phase) {
            if (! $this->phaseDone($phase)) {
                return $phase;
            }
        }

        return null;
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status]['label'] ?? $this->status;
    }

    public function statusColor(): string
    {
        return self::STATUSES[$this->status]['color'] ?? 'secondary';
    }

    /** Minutes from entering theatre to leaving. */
    public function durationMinutes(): ?int
    {
        return $this->in_theatre_at && $this->out_at ? (int) $this->in_theatre_at->diffInMinutes($this->out_at) : null;
    }
}
