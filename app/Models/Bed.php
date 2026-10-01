<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Bed extends Model
{
    public const STATUSES = [
        'available' => ['label' => 'Available', 'color' => 'success', 'icon' => 'bi-check-circle'],
        'occupied' => ['label' => 'Occupied', 'color' => 'primary', 'icon' => 'bi-person-fill'],
        'cleaning' => ['label' => 'Needs cleaning', 'color' => 'warning', 'icon' => 'bi-stars'],
        'out_of_service' => ['label' => 'Out of service', 'color' => 'secondary', 'icon' => 'bi-slash-circle'],
    ];

    protected $fillable = ['label', 'status'];

    public function ward(): BelongsTo
    {
        return $this->belongsTo(Ward::class);
    }

    public function currentAdmission(): HasOne
    {
        return $this->hasOne(Admission::class)->where('status', 'admitted');
    }
}
