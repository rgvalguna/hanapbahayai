<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Recommendation extends Model
{
    use HasUuids;

    protected $fillable = [
        'consultation_id',
        'listing_id',
        'score_total',
        'scores',
        'rationale',
        'warnings',
        'surfaced_at',
    ];

    protected $casts = [
        'score_total' => 'float',
        'scores' => 'array',
        'warnings' => 'array',
        'surfaced_at' => 'datetime',
    ];

    public function consultation(): BelongsTo
    {
        return $this->belongsTo(Consultation::class);
    }

    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }
}
