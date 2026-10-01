<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Consultation extends Model
{
    use Auditable;

    /**
     * Note sections in display order: field => [label, placeholder].
     */
    public const SECTIONS = [
        'presenting_complaint' => ['Presenting complaint', 'Main complaint(s) and duration, in the patient\'s words'],
        'history' => ['History of presenting complaint', 'Onset, character, progression, associated symptoms…'],
        'past_history' => ['Past medical & surgical history', 'Chronic illnesses, admissions, operations, transfusions'],
        'drug_history' => ['Drug history', 'Current medications, herbal remedies, adherence'],
        'family_social_history' => ['Family & social history', 'Family illnesses, occupation, smoking, alcohol, living situation'],
        'systems_review' => ['Review of systems', 'Positive and relevant negative findings by system'],
        'examination' => ['Examination findings', 'General appearance and systemic examination'],
        'assessment' => ['Assessment / impression', 'Clinical reasoning and summary'],
        'plan' => ['Plan', 'Investigations, treatment, advice, follow-up'],
    ];

    protected $fillable = [
        'presenting_complaint', 'history', 'past_history', 'drug_history', 'family_social_history',
        'systems_review', 'examination', 'assessment', 'plan',
    ];

    protected function casts(): array
    {
        return ['signed_at' => 'datetime'];
    }

    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'doctor_id');
    }

    public function diagnoses(): HasMany
    {
        return $this->hasMany(Diagnosis::class)->orderByDesc('is_primary')->orderBy('id');
    }

    public function addenda(): HasMany
    {
        return $this->hasMany(ConsultationAddendum::class)->oldest();
    }

    public function labOrders(): HasMany
    {
        return $this->hasMany(LabOrder::class);
    }

    public function imagingOrders(): HasMany
    {
        return $this->hasMany(ImagingOrder::class);
    }

    public function prescriptions(): HasMany
    {
        return $this->hasMany(Prescription::class);
    }

    public function isSigned(): bool
    {
        return $this->status === 'signed';
    }

    /**
     * Only the owning doctor may edit, and only until signed.
     */
    public function isEditableBy(?User $user): bool
    {
        return ! $this->isSigned() && $user && $user->id === $this->doctor_id && $user->can('consultations.create');
    }
}
