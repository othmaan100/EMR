<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MedicationAdministration extends Model
{
    use Auditable;

    public const STATUSES = [
        'given' => ['label' => 'Given', 'color' => 'success'],
        'refused' => ['label' => 'Refused', 'color' => 'warning'],
        'held' => ['label' => 'Held / omitted', 'color' => 'secondary'],
    ];

    protected $fillable = ['prescription_item_id', 'status', 'dose_given', 'note', 'administered_at'];

    protected function casts(): array
    {
        return ['administered_at' => 'datetime'];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(PrescriptionItem::class, 'prescription_item_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
