<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PhysioSession extends Model
{
    protected $fillable = ['session_date', 'treatments', 'pain_before', 'pain_after', 'notes'];

    protected function casts(): array
    {
        return ['session_date' => 'date', 'treatments' => 'array', 'pain_before' => 'integer', 'pain_after' => 'integer'];
    }

    public function episode(): BelongsTo
    {
        return $this->belongsTo(PhysioEpisode::class, 'physio_episode_id');
    }

    public function therapist(): BelongsTo
    {
        return $this->belongsTo(User::class, 'therapist_id');
    }

    public function treatmentLabels(): string
    {
        $labels = config('emr.specialty.physio_treatments');

        return collect($this->treatments)->map(fn ($t) => $labels[$t] ?? $t)->implode(', ');
    }
}
