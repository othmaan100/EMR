<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PostnatalVisit extends Model
{
    use Auditable;

    protected $fillable = ['visit_date', 'systolic', 'diastolic', 'temperature', 'uterus', 'lochia', 'breastfeeding', 'wound',
        'mood_concern', 'family_planning', 'baby_weight_g', 'cord', 'jaundice', 'notes'];

    protected function casts(): array
    {
        return ['visit_date' => 'date', 'mood_concern' => 'boolean', 'jaundice' => 'boolean', 'temperature' => 'float'];
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
