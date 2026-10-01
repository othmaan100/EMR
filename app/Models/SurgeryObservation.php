<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SurgeryObservation extends Model
{
    protected $fillable = ['recorded_at', 'pulse', 'systolic', 'diastolic', 'spo2', 'notes'];

    protected function casts(): array
    {
        return ['recorded_at' => 'datetime'];
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function isAbnormal(): bool
    {
        return ($this->spo2 !== null && $this->spo2 < 94)
            || ($this->systolic !== null && ($this->systolic < 90 || $this->systolic >= 180))
            || ($this->pulse !== null && ($this->pulse < 50 || $this->pulse > 120));
    }
}
