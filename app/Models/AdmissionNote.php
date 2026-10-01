<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdmissionNote extends Model
{
    use Auditable;

    public const TYPES = [
        'ward_round' => ['label' => 'Ward round', 'color' => 'primary'],
        'progress' => ['label' => 'Progress note', 'color' => 'info'],
        'nursing' => ['label' => 'Nursing', 'color' => 'success'],
    ];

    protected $fillable = ['type', 'note'];

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
