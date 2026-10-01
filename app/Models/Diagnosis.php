<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Diagnosis extends Model
{
    use Auditable;

    protected $table = 'diagnoses';

    public const CERTAINTY = [
        'provisional' => ['label' => 'Provisional', 'color' => 'warning'],
        'confirmed' => ['label' => 'Confirmed', 'color' => 'success'],
        'differential' => ['label' => 'Differential', 'color' => 'secondary'],
    ];

    protected $fillable = ['icd10_code', 'description', 'certainty', 'is_primary'];

    protected function casts(): array
    {
        return ['is_primary' => 'boolean'];
    }

    public function consultation(): BelongsTo
    {
        return $this->belongsTo(Consultation::class);
    }
}
