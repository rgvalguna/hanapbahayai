<?php

namespace App\Modules\Financial;

/**
 * Philippine property transaction cost calculator.
 * Covers all costs a buyer must budget beyond the purchase price.
 */
final class HiddenCosts
{
    // Documentary Stamp Tax (buyer-side on loan)
    public const DST_RATE             = 0.015;  // 1.5%
    // Local transfer tax
    public const TRANSFER_TAX_PROVINCE = 0.005; // 0.5%
    public const TRANSFER_TAX_CITY     = 0.0075;// 0.75%
    // Title registration
    public const REGISTRATION_RATE     = 0.0025; // ~0.25%
    // Notarial fee
    public const NOTARIAL_RATE         = 0.001;  // ~0.1%
    // Broker commission (typically seller's cost, but in practice often negotiated)
    public const BROKER_COMMISSION     = 0.03;   // 3%
    // Capital Gains Tax (seller-side, but buyers need to know)
    public const CGT_RATE              = 0.06;   // 6%

    /**
     * @param  float  $purchasePrice      Total contract price in PHP
     * @param  float  $loanAmount         Loan amount (for DST computation)
     * @param  bool   $isCity             True if property is in a city (higher local transfer tax)
     * @param  bool   $buyerPaysBroker    If true, include broker commission as buyer cost
     * @param  float  $condoAssocMoveIn   Condo association move-in dues (varies per development)
     * @param  float  $movingCostEstimate Estimated moving costs (default PHP 25,000)
     * @return array{
     *     dst: float,
     *     transfer_tax: float,
     *     registration: float,
     *     notarial: float,
     *     broker_commission: float,
     *     moving: float,
     *     assoc_move_in: float,
     *     total: float,
     *     note: string
     * }
     */
    public static function breakdown(
        float $purchasePrice,
        float $loanAmount         = 0,
        bool  $isCity             = true,
        bool  $buyerPaysBroker    = false,
        float $condoAssocMoveIn   = 0,
        float $movingCostEstimate = 25_000,
    ): array {
        $dst            = round($loanAmount * self::DST_RATE, 2);
        $transferTaxRate = $isCity ? self::TRANSFER_TAX_CITY : self::TRANSFER_TAX_PROVINCE;
        $transferTax    = round($purchasePrice * $transferTaxRate, 2);
        $registration   = round($purchasePrice * self::REGISTRATION_RATE, 2);
        $notarial       = round($purchasePrice * self::NOTARIAL_RATE, 2);
        $brokerComm     = $buyerPaysBroker ? round($purchasePrice * self::BROKER_COMMISSION, 2) : 0.0;
        $moving         = $movingCostEstimate;
        $assocMoveIn    = $condoAssocMoveIn;

        $total = $dst + $transferTax + $registration + $notarial + $brokerComm + $moving + $assocMoveIn;

        return [
            'dst'               => $dst,
            'transfer_tax'      => $transferTax,
            'registration'      => $registration,
            'notarial'          => $notarial,
            'broker_commission' => $brokerComm,
            'moving'            => $moving,
            'assoc_move_in'     => $assocMoveIn,
            'total'             => round($total, 2),
            'note'              => 'CGT '.round(self::CGT_RATE * 100, 1).'% is a seller-side cost. Verify actual BIR zonal vs contract price — higher applies for tax base.',
        ];
    }
}
