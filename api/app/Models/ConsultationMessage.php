<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConsultationMessage extends Model
{
    use HasUuids;

    protected $fillable = [
        'consultation_id',
        'role',
        'content',
        'tool_calls',
        'tool_call_id',
        'tokens_in',
        'tokens_out',
        'cache_read_tokens',
        'cache_write_tokens',
        'model',
    ];

    protected $casts = [
        'tool_calls' => 'array',
        'tokens_in' => 'integer',
        'tokens_out' => 'integer',
        'cache_read_tokens' => 'integer',
        'cache_write_tokens' => 'integer',
    ];

    public function consultation(): BelongsTo
    {
        return $this->belongsTo(Consultation::class);
    }
}
