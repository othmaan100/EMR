<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImagingAttachment extends Model
{
    public const DISK = 'local'; // private: served only through an authorised route

    protected $fillable = ['path', 'original_name', 'mime', 'size', 'caption'];

    public function order(): BelongsTo
    {
        return $this->belongsTo(ImagingOrder::class, 'imaging_order_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function isImage(): bool
    {
        return str_starts_with($this->mime, 'image/');
    }
}
