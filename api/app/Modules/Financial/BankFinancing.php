<?php

namespace App\Modules\Financial;

/**
 * Bank housing loan simulator.
 * Models a two-phase structure: teaser rate (fixed years) + repriced rate.
 * Supported banks have preset rate matrices; custom rates also accepted.
 */
final class BankFinancing
{
    /**
     * Preset rate matrices per bank.
     * Structure: ['teaserRate', 'teaserYears', 'repricedRate']
     *
     * @var array<string, array{teaser_rate: float, teaser_years: int, repriced_rate: float}>
     */
    public const BANK_PRESETS = [
        'bpi'           => ['teaser_rate' => 5.50, 'teaser_years' => 3, 'repriced_rate' => 7.50],
        'bdo'           => ['teaser_rate' => 5.25, 'teaser_years' => 3, 'repriced_rate' => 7.25],
        'metrobank'     => ['teaser_rate' => 5.50, 'teaser_years' => 3, 'repriced_rate' => 7.50],
        'security_bank' => ['teaser_rate' => 5.00, 'teaser_years' => 5, 'repriced_rate' => 7.00],
        'rcbc'          => ['teaser_rate' => 5.25, 'teaser_years' => 3, 'repriced_rate' => 7.25],
        'pnb'           => ['teaser_rate' => 5.50, 'teaser_years' => 3, 'repriced_rate' => 7.50],
    ];

    /**
     * Simulate a bank housing loan with teaser + repriced phases.
     *
     * @param  float       $loanAmount       Principal in PHP
     * @param  int         $termYears        Total loan term in years
     * @param  float       $teaserRatePct    Fixed teaser interest rate (%)
     * @param  int         $teaserYears      Number of years at teaser rate
     * @param  float       $repricedRatePct  Interest rate after repricing (%)
     * @return array{
     *     teaser_rate_pct: float,
     *     teaser_years: int,
     *     repriced_rate_pct: float,
     *     teaser_monthly: float,
     *     repriced_monthly: float,
     *     balance_at_repricing: float,
     *     total_teaser_payments: float,
     *     total_repriced_payments: float,
     *     total_cost: float,
     *     total_interest: float,
     *     five_year_summary: array{total_paid: float, principal_paid: float, interest_paid: float, remaining_balance: float}
     * }
     */
    public static function simulate(
        float $loanAmount,
        int   $termYears,
        float $teaserRatePct,
        int   $teaserYears,
        float $repricedRatePct,
    ): array {
        $teaserMonths   = $teaserYears * 12;
        $totalMonths    = $termYears * 12;
        $repricedMonths = $totalMonths - $teaserMonths;

        // Phase 1: teaser rate over full remaining term to get monthly payment
        $teaserMonthly = Amortization::monthlyPayment($loanAmount, $teaserRatePct, $totalMonths);

        // Balance at end of teaser period
        $balanceAtRepricing = self::balanceAfterMonths($loanAmount, $teaserRatePct, $totalMonths, $teaserMonths);

        // Phase 2: repriced rate on remaining balance for remaining term
        $repricedMonthly = $repricedMonths > 0
            ? Amortization::monthlyPayment($balanceAtRepricing, $repricedRatePct, $repricedMonths)
            : 0.0;

        $totalTeaserPaid   = round($teaserMonthly * $teaserMonths, 2);
        $totalRepricedPaid = round($repricedMonthly * $repricedMonths, 2);
        $totalCost         = round($totalTeaserPaid + $totalRepricedPaid, 2);
        $totalInterest     = round($totalCost - $loanAmount, 2);

        // Five-year snapshot (common comparison horizon)
        $fiveYearMonths    = min(60, $totalMonths);
        $fiveYearTeaserMos = min($teaserMonths, $fiveYearMonths);
        $fiveYearRePricedMos = max(0, $fiveYearMonths - $fiveYearTeaserMos);

        $fiveYearPaid      = round(
            ($teaserMonthly * $fiveYearTeaserMos) + ($repricedMonthly * $fiveYearRePricedMos),
            2
        );
        $balanceAt5Yrs     = $fiveYearMonths <= $teaserMonths
            ? self::balanceAfterMonths($loanAmount, $teaserRatePct, $totalMonths, $fiveYearMonths)
            : self::balanceAfterMonths($balanceAtRepricing, $repricedRatePct, $repricedMonths, $fiveYearRePricedMos);

        $principalPaidAt5  = round($loanAmount - $balanceAt5Yrs, 2);
        $interestPaidAt5   = round($fiveYearPaid - $principalPaidAt5, 2);

        return [
            'teaser_rate_pct'        => $teaserRatePct,
            'teaser_years'           => $teaserYears,
            'repriced_rate_pct'      => $repricedRatePct,
            'teaser_monthly'         => round($teaserMonthly, 2),
            'repriced_monthly'       => round($repricedMonthly, 2),
            'balance_at_repricing'   => round($balanceAtRepricing, 2),
            'total_teaser_payments'  => $totalTeaserPaid,
            'total_repriced_payments'=> $totalRepricedPaid,
            'total_cost'             => $totalCost,
            'total_interest'         => $totalInterest,
            'five_year_summary'      => [
                'total_paid'        => $fiveYearPaid,
                'principal_paid'    => $principalPaidAt5,
                'interest_paid'     => $interestPaidAt5,
                'remaining_balance' => round($balanceAt5Yrs, 2),
            ],
        ];
    }

    /**
     * Convenience factory: simulate using a preset bank name.
     *
     * @param  string $bank  One of the keys in BANK_PRESETS
     */
    public static function simulatePreset(float $loanAmount, int $termYears, string $bank): array
    {
        $preset = self::BANK_PRESETS[$bank]
            ?? throw new \InvalidArgumentException("Unknown bank preset: {$bank}");

        return self::simulate(
            $loanAmount,
            $termYears,
            $preset['teaser_rate'],
            $preset['teaser_years'],
            $preset['repriced_rate'],
        );
    }

    /**
     * Outstanding balance after N payments (standard amortization formula).
     */
    private static function balanceAfterMonths(
        float $principal,
        float $annualRatePct,
        int   $totalMonths,
        int   $paymentsMade,
    ): float {
        if ($annualRatePct <= 0) {
            return $principal - ($principal / $totalMonths * $paymentsMade);
        }

        $r      = ($annualRatePct / 100) / 12;
        $factor = (1 + $r) ** $totalMonths;
        $paid   = (1 + $r) ** $paymentsMade;

        return $principal * ($factor - $paid) / ($factor - 1);
    }
}
