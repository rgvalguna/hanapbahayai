<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Developer extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'slug', 'website', 'description', 'reputation_score',
        'total_projects', 'completed_on_time', 'active_complaints', 'is_verified',
    ];

    protected function casts(): array
    {
        return [
            'is_verified'    => 'boolean',
            'reputation_score' => 'float',
        ];
    }

    public function listings(): HasMany
    {
        return $this->hasMany(Listing::class);
    }
}
