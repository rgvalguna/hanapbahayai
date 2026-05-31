<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'archetype', 'modifiers', 'onboarding_step',
        'family_size', 'num_children', 'children_ages', 'purchase_purpose', 'risk_tolerance',
    ];

    protected function casts(): array
    {
        return [
            'modifiers'     => 'array',
            'children_ages' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
