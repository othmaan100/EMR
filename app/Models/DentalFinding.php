<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DentalFinding extends Model
{
    public const STATUSES = [
        'existing' => ['label' => 'Finding', 'color' => 'secondary'],
        'planned' => ['label' => 'Planned', 'color' => 'warning'],
        'completed' => ['label' => 'Done', 'color' => 'success'],
        'cancelled' => ['label' => 'Cancelled', 'color' => 'light'],
    ];

    protected $fillable = ['tooth', 'surfaces', 'condition', 'status', 'service_id', 'notes'];

    protected function casts(): array
    {
        return ['completed_at' => 'datetime', 'tooth' => 'integer'];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function completer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public function conditionLabel(): ?string
    {
        return $this->condition ? (config("emr.specialty.dental_conditions.{$this->condition}.0") ?? $this->condition) : null;
    }

    public function toothLabel(): string
    {
        return $this->tooth ? "Tooth {$this->tooth}" : 'Whole mouth';
    }
}
