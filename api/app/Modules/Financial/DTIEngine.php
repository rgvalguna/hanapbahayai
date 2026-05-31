<?php

namespace App\Modules\Financial;

/**
 * Debt-to-Income (DTI) calculator.
 *
 * Philippine bank standard: DTI should not exceed 30–35% of gross monthly income.
 * Pag-IBIG is slightly more lenient (up to 40% for high-income borrowers).
 */
final class DTIEngine
{
    public const DTI_SAFE     = 0.30;
    public const DTI_CAUTION  = 0.35;
    public const DTI_WARNING  = 0.40;
    // Above WARNING → CRITICAL

    /**
     * @param  float $proposedMonthlyPayment  New monthly housing amortization
     * @param  float $existingObligations      Existing monthly debt payments (car, personal loans, CC minimums)
     * @param  float $grossMonthlyIncome        Total gross monthly income (primary + co-borrower)
     * @param  float $annualRatePct             For computing recommended max loan back-calculation
     * @param  int   $termMonths               Loan term in months for back-calculation
     * @return array{
     *     ratio: float,
     *     status: 'safe'|'caution'|'warning'|'critical',
     *     message: string,
     *     recommended_max_loan: float,
     *     max_monthly_payment: float,
     * }
     */
    public static function evaluate(
        float $proposedMonthlyPayment,
        float $existingObligations,
        float $grossMonthlyIncome,
        float $annualRatePct,
        int   $termMonths,
    ): array {
        if ($grossMonthlyIncome <= 0) {
            return [
                'ratio'                => 0,
                'status'               => 'critical',
                'message'              => 'Gross monthly income must be positive.',
                'recommended_max_loan' => 0.0,
                'max_monthly_payment'  => 0.0,
            ];
        }

        $totalObligations = $proposedMonthlyPayment + $existingObligations;
        $ratio            = $totalObligations / $grossMonthlyIncome;

        [$status, $message] = match (true) {
            $ratio <= self::DTI_SAFE    => ['safe',     'DTI is within safe limits. Strong loan candidacy.'],
            $ratio <= self::DTI_CAUTION => ['caution',  'DTI is slightly elevated. Consider a longer term or higher down payment.'],
            $ratio <= self::DTI_WARNING => ['warning',  'DTI is high. Approval may require additional collateral or a guarantor.'],
            default                     => ['critical', 'DTI exceeds 40%. Loan is very unlikely to be approved at this price point.'],
        };

        // Max affordable monthly payment at safe threshold
        $maxMonthly        = max(0.0, ($grossMonthlyIncome * self::DTI_SAFE) - $existingObligations);
        $recommendedMaxLoan = Amortization::maxAffordableLoan($maxMonthly, $annualRatePct, $termMonths);

        return [
            'ratio'                => round($ratio, 4),
            'status'               => $status,
            'message'              => $message,
            'recommended_max_loan' => round($recommendedMaxLoan, 2),
            'max_monthly_payment'  => round($maxMonthly, 2),
        ];
    }
}
