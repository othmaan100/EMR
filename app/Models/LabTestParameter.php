<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LabTestParameter extends Model
{
    use Auditable;

    public const TYPES = ['numeric' => 'Number', 'option' => 'Choice list', 'text' => 'Free text'];

    protected $fillable = ['name', 'unit', 'type', 'options', 'ref_low', 'ref_high', 'ref_text', 'sort_order'];

    protected function casts(): array
    {
        return ['options' => 'array', 'ref_low' => 'float', 'ref_high' => 'float'];
    }

    public function test(): BelongsTo
    {
        return $this->belongsTo(LabTest::class, 'lab_test_id');
    }

    /**
     * Human-readable reference, e.g. "4 – 11", "< 5.2", "> 1", "Negative".
     */
    public function referenceLabel(): ?string
    {
        $fmt = fn ($n) => rtrim(rtrim(number_format($n, 3, '.', ''), '0'), '.');

        return match (true) {
            $this->ref_low !== null && $this->ref_high !== null => $fmt($this->ref_low).' – '.$fmt($this->ref_high),
            $this->ref_high !== null => '< '.$fmt($this->ref_high),
            $this->ref_low !== null => '> '.$fmt($this->ref_low),
            default => $this->ref_text,
        };
    }

    /**
     * low | high | abnormal | null
     */
    public function flagFor(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        if ($this->type === 'numeric' && is_numeric($value)) {
            $n = (float) $value;

            return match (true) {
                $this->ref_low !== null && $n < $this->ref_low => 'low',
                $this->ref_high !== null && $n > $this->ref_high => 'high',
                default => null,
            };
        }

        if ($this->ref_text !== null && strcasecmp($value, $this->ref_text) !== 0) {
            return 'abnormal';
        }

        return null;
    }
}
