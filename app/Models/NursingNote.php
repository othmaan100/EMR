<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NursingNote extends Model
{
    use Auditable;

    public const TYPES = [
        'triage' => 'Triage',
        'general' => 'General',
        'observation' => 'Observation',
        'procedure' => 'Procedure',
        'medication' => 'Medication',
        'handover' => 'Handover',
    ];

    protected $fillable = ['type', 'note'];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
