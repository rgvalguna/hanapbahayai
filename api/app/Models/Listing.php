<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Scout\Searchable;

class Listing extends Model
{
    use HasFactory, HasUuids, Searchable, SoftDeletes;

    protected $fillable = [
        'external_id', 'source', 'status', 'property_type', 'tenure_type',
        'title', 'description', 'slug', 'price_php', 'price_per_sqm',
        'floor_area_sqm', 'lot_area_sqm', 'bedrooms', 'bathrooms', 'parking_slots',
        'address', 'developer_id', 'broker_id', 'photos', 'fraud_flags',
        'fraud_score', 'is_verified', 'amenity_tags', 'score_cache', 'scored_at',
    ];

    protected function casts(): array
    {
        return [
            'address'      => 'array',
            'photos'       => 'array',
            'fraud_flags'  => 'array',
            'amenity_tags' => 'array',
            'score_cache'  => 'array',
            'is_verified'  => 'boolean',
            'price_php'    => 'decimal:2',
            'fraud_score'  => 'float',
            'scored_at'    => 'datetime',
        ];
    }

    public function developer(): BelongsTo
    {
        return $this->belongsTo(Developer::class);
    }

    public function broker(): BelongsTo
    {
        return $this->belongsTo(Broker::class);
    }

    public function embedding(): HasOne
    {
        return $this->hasOne(ListingEmbedding::class);
    }

    public function shortlistEntries(): HasMany
    {
        return $this->hasMany(ShortlistListing::class);
    }

    public function toSearchableArray(): array
    {
        return [
            'id'            => $this->id,
            'title'         => $this->title,
            'description'   => $this->description,
            'property_type' => $this->property_type,
            'status'        => $this->status,
            'price_php'     => (float) $this->price_php,
            'bedrooms'      => $this->bedrooms,
            'address'       => $this->address,
            'is_verified'   => $this->is_verified,
        ];
    }
}
