<?php

namespace App\Modules\Financial;

use InvalidArgumentException;

/**
 * Pure amortization math — no Eloquent, no I/O.
 * All inputs/outputs in PHP native types so this is trivially unit-testable.
 */
final class Amortization
{
    /**
     * Calculate the fixed monthly payment (PMT formula).
     *
     * PMT = P * [r(1+r)^n] / [(1+r)^n - 1]
     *
     * @param  float $principal      Loan principal in PHP
     * @param  float $annualRatePct  Annual interest rate as a percentage (e.g. 6.5 for 6.5%)
     * @param  int   $termMonths     Loan term in months
     */
    public static function monthlyPayment(float $principal, float $annualRatePct, int $termMonths): float
    {
        if ($principal <= 0 || $termMonths <= 0) {
            throw new InvalidArgumentException('Principal and term must be positive.');
        }

        if ($annualRatePct <= 0) {
            // Zero-interest loan (some developer in-house financing)
            return $principal / $termMonths;
        }

        $r = ($annualRatePct / 100) / 12;
        $factor = (1 + $r) ** $termMonths;

        return $principal * ($r * $factor) / ($factor - 1);
    }

    /**
     * Generate the full amortization schedule.
     *
     * @return array<int, array{month: int, payment: float, principal: float, interest: float, balance: float}>
     */
    public static function schedule(float $principal, float $annualRatePct, int $termMonths): array
    {
        $payment  = self::monthlyPayment($principal, $annualRatePct, $termMonths);
        $r        = $annualRatePct > 0 ? ($annualRatePct / 100) / 12 : 0;
        $balance  = $principal;
        $schedule = [];

        for ($month = 1; $month <= $termMonths; $month++) {
            $interest  = round($balance * $r, 2);
            $principal_payment = round(min($payment - $interest, $balance), 2);
            $balance   = round($balance - $principal_payment, 2);

            // Last payment rounding correction
            if ($month === $termMonths && abs($balance) < 1.0) {
                $principal_payment += $balance;
                $balance = 0.0;
            }

            $schedule[] = [
                'month'     => $month,
                'payment'   => round($payment, 2),
                'principal' => $principal_payment,
                'interest'  => $interest,
                'balance'   => max(0.0, $balance),
            ];
        }

        return $schedule;
    }

    /**
     * Maximum loan a borrower can afford given their max monthly payment.
     *
     * Inverse of PMT: P = PMT * [(1+r)^n - 1] / [r(1+r)^n]
     */
    public static function maxAffordableLoan(float $maxMonthlyPayment, float $annualRatePct, int $termMonths): float
    {
        if ($maxMonthlyPayment <= 0 || $termMonths <= 0) {
            return 0.0;
        }

        if ($annualRatePct <= 0) {
            return $maxMonthlyPayment * $termMonths;
        }

        $r      = ($annualRatePct / 100) / 12;
        $factor = (1 + $r) ** $termMonths;

        return $maxMonthlyPayment * ($factor - 1) / ($r * $factor);
    }

    /**
     * Total interest paid over the life of the loan.
     */
    public static function totalInterest(float $principal, float $annualRatePct, int $termMonths): float
    {
        $payment = self::monthlyPayment($principal, $annualRatePct, $termMonths);
        return round(($payment * $termMonths) - $principal, 2);
    }
}
