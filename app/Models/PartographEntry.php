<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PartographEntry extends Model
{
    protected $fillable = ['recorded_at', 'cervical_dilation', 'descent', 'contractions', 'contraction_strength', 'fetal_heart_rate',
        'liquor', 'moulding', 'pulse', 'systolic', 'diastolic', 'temperature', 'notes'];

    protected function casts(): array
    {
        return ['recorded_at' => 'datetime', 'cervical_dilation' => 'float', 'temperature' => 'float'];
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /**
     * @return list<string>
     */
    public function alerts(): array
    {
        $alerts = [];
        if ($this->fetal_heart_rate && ($this->fetal_heart_rate < 110 || $this->fetal_heart_rate > 160)) {
            $alerts[] = "FHR {$this->fetal_heart_rate}";
        }
        if (in_array($this->liquor, ['M', 'B'], true)) {
            $alerts[] = $this->liquor === 'M' ? 'Meconium' : 'Blood-stained liquor';
        }
        if ($this->moulding === '+++') {
            $alerts[] = 'Severe moulding';
        }
        if ($this->systolic >= 140 || $this->diastolic >= 90) {
            $alerts[] = "BP {$this->systolic}/{$this->diastolic}";
        }
        if ($this->temperature !== null && $this->temperature >= 38) {
            $alerts[] = "Temp {$this->temperature}";
        }

        return $alerts;
    }
}
