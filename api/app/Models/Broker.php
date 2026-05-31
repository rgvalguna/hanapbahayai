<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Broker extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'prc_license_no', 'status', 'veriff_session_id',
        'license_expires_at', 'specializations', 'avg_rating', 'total_reviews',
    ];

    protected function casts(): array
    {
        return [
            'specializations'    => 'array',
            'license_expires_at' => 'datetime',
            'avg_rating'         => 'float',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function listings(): HasMany
    {
        return $this->hasMany(Listing::class);
    }
}
