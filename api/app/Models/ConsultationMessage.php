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
        'content_blocks',
        'tool_name',
        'tool_input',
        'tool_result',
        'input_tokens',
        'output_tokens',
        'cache_read_tokens',
        'cache_write_tokens',
    ];

    protected $casts = [
        'content_blocks'    => 'array',
        'tool_input'        => 'array',
        'tool_result'       => 'array',
        'input_tokens'      => 'integer',
        'output_tokens'     => 'integer',
        'cache_read_tokens' => 'integer',
        'cache_write_tokens' => 'integer',
    ];

    public function consultation(): BelongsTo
    {
        return $this->belongsTo(Consultation::class);
    }
}
