<?php

namespace App\Modules\Financial;

use InvalidArgumentException;

/**
 * Pag-IBIG (HDMF) housing loan simulator.
 * Based on HDMF Circular 461-B (2024-07).
 * Max loan: PHP 6,000,000. Max term: 30 years. Max maturity age: 75.
 */
final class PagIBIG
{
    public const MAX_LOAN_PHP          = 6_000_000;
    public const MAX_TERM_YEARS        = 30;
    public const MAX_MATURITY_AGE      = 75;
    public const MIN_CONTRIBUTIONS     = 24;   // months
    public const MAX_LTV_END_USER      = 0.90; // 90%

    /** @var array<array{max: float, rate: float}> */
    private const RATE_TIERS = [
        ['max' =>   450_000, 'rate' => 5.375],
        ['max' =>   750_000, 'rate' => 6.375],
        ['max' => 1_000_000, 'rate' => 6.625],
        ['max' => 2_000_000, 'rate' => 6.875],
        ['max' => 4_000_000, 'rate' => 8.000],
        ['max' => 6_000_000, 'rate' => 9.500],
    ];

    /**
     * Simulate a Pag-IBIG housing loan.
     *
     * @param  float  $propertyValue        Appraised property value in PHP
     * @param  float  $grossMonthlyIncome   Combined gross monthly income in PHP
     * @param  int    $borrowerAge          Age of primary borrower at application
     * @param  bool   $pagibigCurrentMember True if at least 24 mos contributions and current
     * @param  int    $contributionMonths   Number of months contributed
     * @param  float  $requestedLoan        Requested loan amount (0 to auto-calc 90% LTV)
     * @param  int    $requestedTermYears   Requested term (1–30 years)
     * @return array{
     *     is_eligible: bool,
     *     ineligibility_reason: string|null,
     *     loan_amount: float,
     *     applicable_rate_pct: float,
     *     effective_term_years: int,
     *     monthly_amortization: float,
     *     total_interest: float,
     *     max_loanable: float,
     * }
     */
    public static function simulate(
        float $propertyValue,
        float $grossMonthlyIncome,
        int   $borrowerAge,
        bool  $pagibigCurrentMember,
        int   $contributionMonths,
        float $requestedLoan      = 0,
        int   $requestedTermYears = 30,
    ): array {
        // ── Eligibility checks ─────────────────────────────────────────
        if ($borrowerAge < 18) {
            return self::ineligible('Borrower must be at least 18 years old.');
        }

        if (!$pagibigCurrentMember) {
            return self::ineligible('Borrower must be an active Pag-IBIG member.');
        }

        if ($contributionMonths < self::MIN_CONTRIBUTIONS) {
            return self::ineligible(
                "Borrower must have at least ".self::MIN_CONTRIBUTIONS." months of Pag-IBIG contributions ".
                "(has {$contributionMonths})."
            );
        }

        if ($borrowerAge >= self::MAX_MATURITY_AGE) {
            return self::ineligible('Borrower has exceeded maximum maturity age of '.self::MAX_MATURITY_AGE.'.');
        }

        // ── Loan amount ────────────────────────────────────────────────
        $maxByLtv      = $propertyValue * self::MAX_LTV_END_USER;
        $maxLoanable   = min($maxByLtv, self::MAX_LOAN_PHP);
        $loanAmount    = $requestedLoan > 0
            ? min($requestedLoan, $maxLoanable)
            : $maxLoanable;

        // ── Term ───────────────────────────────────────────────────────
        $maxTermByAge  = self::MAX_MATURITY_AGE - $borrowerAge;
        $effectiveTerm = min($requestedTermYears, self::MAX_TERM_YEARS, $maxTermByAge);

        if ($effectiveTerm <= 0) {
            return self::ineligible('Effective loan term is zero after age constraint.');
        }

        // ── Rate lookup ────────────────────────────────────────────────
        $rate = self::rateForAmount($loanAmount);

        // ── Compute amortization ───────────────────────────────────────
        $termMonths   = $effectiveTerm * 12;
        $monthly      = Amortization::monthlyPayment($loanAmount, $rate, $termMonths);
        $totalInterest = Amortization::totalInterest($loanAmount, $rate, $termMonths);

        return [
            'is_eligible'           => true,
            'ineligibility_reason'  => null,
            'loan_amount'           => round($loanAmount, 2),
            'applicable_rate_pct'   => $rate,
            'effective_term_years'  => $effectiveTerm,
            'monthly_amortization'  => round($monthly, 2),
            'total_interest'        => round($totalInterest, 2),
            'max_loanable'          => round($maxLoanable, 2),
        ];
    }

    private static function rateForAmount(float $amount): float
    {
        foreach (self::RATE_TIERS as $tier) {
            if ($amount <= $tier['max']) {
                return $tier['rate'];
            }
        }
        return self::RATE_TIERS[array_key_last(self::RATE_TIERS)]['rate'];
    }

    /** @return array{is_eligible: false, ineligibility_reason: string, loan_amount: 0, applicable_rate_pct: 0, effective_term_years: 0, monthly_amortization: 0, total_interest: 0, max_loanable: 0} */
    private static function ineligible(string $reason): array
    {
        return [
            'is_eligible'           => false,
            'ineligibility_reason'  => $reason,
            'loan_amount'           => 0,
            'applicable_rate_pct'   => 0,
            'effective_term_years'  => 0,
            'monthly_amortization'  => 0,
            'total_interest'        => 0,
            'max_loanable'          => 0,
        ];
    }
}
