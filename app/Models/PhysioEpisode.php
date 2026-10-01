<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PhysioEpisode extends Model
{
    protected $fillable = ['region', 'complaint', 'assessment', 'pain_initial', 'goals', 'plan', 'sessions_planned'];

    protected function casts(): array
    {
        return ['discharged_at' => 'datetime', 'pain_initial' => 'integer', 'sessions_planned' => 'integer'];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(PhysioSession::class)->orderBy('session_date')->orderBy('id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function outcomeLabel(): ?string
    {
        return $this->outcome ? (config("emr.specialty.physio_outcomes.{$this->outcome}") ?? $this->outcome) : null;
    }

    /**
     * Pain now vs at assessment, from the last session that recorded it.
     */
    public function latestPain(): ?int
    {
        $last = $this->sessions->whereNotNull('pain_after')->last();

        return $last?->pain_after;
    }
}
