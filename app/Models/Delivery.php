<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Delivery extends Model
{
    use Auditable;

    protected $fillable = ['delivered_at', 'mode', 'gestation_weeks', 'blood_loss_ml', 'placenta_complete', 'perineum',
        'complications', 'maternal_outcome', 'notes', 'admission_id'];

    protected function casts(): array
    {
        return ['delivered_at' => 'datetime', 'placenta_complete' => 'boolean', 'complications' => 'array'];
    }

    public function pregnancy(): BelongsTo
    {
        return $this->belongsTo(Pregnancy::class);
    }

    public function babies(): HasMany
    {
        return $this->hasMany(Baby::class);
    }

    public function attendant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'attended_by');
    }

    public function modeLabel(): string
    {
        return config("emr.maternity.delivery_modes.{$this->mode}", $this->mode);
    }

    public function isCaesarean(): bool
    {
        return str_starts_with($this->mode, 'cs_');
    }
}
