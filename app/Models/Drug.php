<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasPrices;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Drug extends Model
{
    use Auditable, HasPrices;

    /** Batches expiring within this many days are flagged. */
    public const EXPIRY_WARNING_DAYS = 90;

    protected $fillable = ['name', 'strength', 'form', 'route', 'dispensing_unit', 'reorder_level', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /**
     * Blank means "no reorder alert" (0).
     */
    protected function reorderLevel(): Attribute
    {
        return Attribute::make(get: fn ($v) => (int) $v, set: fn ($v) => (int) ($v ?? 0));
    }

    /**
     * e.g. "Amoxicillin 500mg Capsule"
     */
    protected function label(): Attribute
    {
        return Attribute::get(fn () => trim("{$this->name} {$this->strength} {$this->form}"));
    }

    /**
     * Unit used when counting stock (defaults from the dosage form).
     */
    protected function unit(): Attribute
    {
        return Attribute::get(fn () => $this->dispensing_unit ?: strtolower(strtok($this->form, ' (')));
    }

    public function batches(): HasMany
    {
        return $this->hasMany(StockBatch::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    /**
     * Usable (unexpired) quantity on hand.
     */
    public function usableStock(): int
    {
        return (int) $this->batches()->where('quantity_on_hand', '>', 0)->whereDate('expiry_date', '>=', today())->sum('quantity_on_hand');
    }

    /**
     * Adds `stock_on_hand` (unexpired) and `next_expiry` columns.
     */
    public function scopeWithStock(Builder $query): void
    {
        $usable = fn ($q) => $q->where('quantity_on_hand', '>', 0)->whereDate('expiry_date', '>=', today());

        $query->withSum(['batches as stock_on_hand' => $usable], 'quantity_on_hand')
            ->withMin(['batches as next_expiry' => $usable], 'expiry_date');
    }

    /**
     * Active drugs with a reorder level whose usable stock has fallen to it.
     */
    public function scopeLowStock(Builder $query): void
    {
        $query->where('is_active', true)->where('reorder_level', '>', 0)->whereRaw(
            'COALESCE((SELECT SUM(quantity_on_hand) FROM stock_batches WHERE stock_batches.drug_id = drugs.id'
            .' AND quantity_on_hand > 0 AND expiry_date >= ?), 0) <= reorder_level',
            [today()->toDateString()]
        );
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }
}
