<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Prescription extends Model
{
    use Auditable;

    public const STATUSES = [
        'pending' => ['label' => 'Awaiting dispensing', 'color' => 'primary'],
        'partially_dispensed' => ['label' => 'Partially dispensed', 'color' => 'warning'],
        'dispensed' => ['label' => 'Dispensed', 'color' => 'success'],
        'cancelled' => ['label' => 'Cancelled', 'color' => 'dark'],
    ];

    public const FREQUENCIES = [
        'OD' => 'Once daily',
        'BD' => 'Twice daily',
        'TDS' => 'Three times daily',
        'QDS' => 'Four times daily',
        'NOCTE' => 'At night',
        'MANE' => 'In the morning',
        'PRN' => 'When required',
        'STAT' => 'Immediately (once)',
        'WEEKLY' => 'Once weekly',
    ];

    public const ROUTES = ['Oral', 'IV', 'IM', 'SC', 'Topical', 'Inhaled', 'Rectal', 'Vaginal', 'Sublingual', 'Eye', 'Ear', 'Nasal'];

    protected $fillable = ['notes'];

    protected function casts(): array
    {
        return ['dispensed_at' => 'datetime'];
    }

    public function dispenser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dispensed_by');
    }

    /**
     * pending → partially_dispensed → dispensed, from the items' progress.
     */
    public function refreshStatus(): void
    {
        $items = $this->items()->get();

        $this->status = match (true) {
            $items->every(fn (PrescriptionItem $i) => $i->isComplete()) => 'dispensed',
            $items->contains(fn (PrescriptionItem $i) => $i->quantity_dispensed > 0 || $i->not_dispensed_reason) => 'partially_dispensed',
            default => 'pending',
        };
        $this->save();
    }

    public function items(): HasMany
    {
        return $this->hasMany(PrescriptionItem::class);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function prescriber(): BelongsTo
    {
        return $this->belongsTo(User::class, 'prescribed_by');
    }

    public function admission(): BelongsTo
    {
        return $this->belongsTo(Admission::class);
    }

    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class);
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status]['label'] ?? $this->status;
    }

    public function statusColor(): string
    {
        return self::STATUSES[$this->status]['color'] ?? 'secondary';
    }
}
