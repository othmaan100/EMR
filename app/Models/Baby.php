<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Baby extends Model
{
    protected $fillable = ['sex', 'birth_weight_g', 'apgar_1', 'apgar_5', 'outcome', 'resuscitated', 'notes'];

    protected function casts(): array
    {
        return ['resuscitated' => 'boolean'];
    }

    public function delivery(): BelongsTo
    {
        return $this->belongsTo(Delivery::class);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function isLiveBirth(): bool
    {
        return $this->outcome === 'live_birth';
    }

    public function isLowBirthWeight(): bool
    {
        return $this->birth_weight_g !== null && $this->birth_weight_g < 2500;
    }

    public function outcomeLabel(): string
    {
        return config("emr.maternity.baby_outcomes.{$this->outcome}", $this->outcome);
    }
}
