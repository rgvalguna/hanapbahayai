<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserFinance extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'version', 'is_current', 'gross_monthly_income_php', 'currency',
        'employment_type', 'has_co_borrower', 'co_borrower_income_php',
        'monthly_obligations_php', 'available_down_payment_php', 'monthly_savings_php',
        'pagibig_member', 'pagibig_contributions_months', 'pagibig_contributions_current',
    ];

    protected function casts(): array
    {
        return [
            'is_current'                   => 'boolean',
            'has_co_borrower'              => 'boolean',
            'pagibig_member'               => 'boolean',
            'pagibig_contributions_current' => 'boolean',
            'gross_monthly_income_php'     => 'decimal:2',
            'co_borrower_income_php'       => 'decimal:2',
            'monthly_obligations_php'      => 'decimal:2',
            'available_down_payment_php'   => 'decimal:2',
            'monthly_savings_php'          => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
