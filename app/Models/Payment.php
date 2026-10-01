<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Payment extends Model
{
    use Auditable;

    public const METHODS = [
        'cash' => 'Cash',
        'card' => 'Card / POS',
        'transfer' => 'Bank transfer',
        'mobile_money' => 'Mobile money',
        'cheque' => 'Cheque',
        'online' => 'Online (card / bank via payment gateway)',
    ];

    protected $fillable = ['amount', 'method', 'reference'];

    /**
     * Methods a cashier can record by hand ("online" comes only from a gateway).
     */
    public static function cashierMethods(): array
    {
        return array_diff_key(self::METHODS, ['online' => true]);
    }

    protected function casts(): array
    {
        return ['amount' => 'float', 'voided_at' => 'datetime'];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function voider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
    }

    public function isVoided(): bool
    {
        return $this->voided_at !== null;
    }
}
