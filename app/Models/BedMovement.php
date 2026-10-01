<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BedMovement extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['from_bed_id', 'to_bed_id', 'reason', 'user_id'];

    public function fromBed(): BelongsTo
    {
        return $this->belongsTo(Bed::class, 'from_bed_id');
    }

    public function toBed(): BelongsTo
    {
        return $this->belongsTo(Bed::class, 'to_bed_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
