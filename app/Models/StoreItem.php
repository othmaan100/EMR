<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A general-stores item (not a drug). Stock is one balance per item,
 * valued at weighted average cost.
 */
class StoreItem extends Model
{
    use Auditable;

    public const CATEGORIES = ['Medical consumables', 'Laboratory reagents', 'Linen & uniforms', 'Stationery', 'Cleaning & sanitation',
        'Kitchen & catering', 'Maintenance & spares', 'Office equipment', 'Other'];

    protected $fillable = ['code', 'name', 'category', 'unit', 'reorder_level', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'reorder_level' => 'integer', 'quantity_on_hand' => 'integer', 'average_cost' => 'float'];
    }

    public function movements(): HasMany
    {
        return $this->hasMany(StoreMovement::class)->latest('id');
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    public function scopeLowStock(Builder $query): void
    {
        $query->where('is_active', true)->where('reorder_level', '>', 0)->whereColumn('quantity_on_hand', '<=', 'reorder_level');
    }

    public function isLow(): bool
    {
        return $this->reorder_level > 0 && $this->quantity_on_hand <= $this->reorder_level;
    }

    public function value(): float
    {
        return round(max(0, $this->quantity_on_hand) * $this->average_cost, 2);
    }

    public function getLabelAttribute(): string
    {
        return "{$this->name} ({$this->unit})";
    }
}
