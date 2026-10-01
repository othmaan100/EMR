<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Icd10Code extends Model
{
    protected $table = 'icd10_codes';

    protected $primaryKey = 'code';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = ['code', 'description'];

    public function scopeSearch(Builder $query, string $term): void
    {
        $term = trim($term);
        $query->where(function (Builder $q) use ($term) {
            $q->where('code', 'like', $term.'%')
                // Every word must appear: "malaria falciparum" finds B50.x.
                ->orWhere(function (Builder $d) use ($term) {
                    foreach (preg_split('/\s+/', $term) as $word) {
                        $d->where('description', 'like', "%$word%");
                    }
                });
        });
        // Rank: code prefix matches first, then all-words-in-description.
        $query->orderByRaw('CASE WHEN code LIKE ? THEN 0 WHEN description LIKE ? THEN 1 ELSE 2 END', [$term.'%', "%$term%"])
            ->orderBy('code');
    }
}
