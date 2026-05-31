<?php

namespace App\Http\Controllers;

use App\Modules\Financial\Amortization;
use App\Modules\Financial\BankFinancing;
use App\Modules\Financial\DTIEngine;
use App\Modules\Financial\HiddenCosts;
use App\Modules\Financial\PagIBIG;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FinancialController extends Controller
{
    public function simulate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'property_price'   => 'required|numeric|min:100000',
            'down_payment'     => 'required|numeric|min:0',
            'annual_rate_pct'  => 'required|numeric|min:0.1|max:30',
            'term_years'       => 'required|integer|min:1|max:35',
            'monthly_income'   => 'nullable|numeric|min:0',
            'existing_debts'   => 'nullable|numeric|min:0',
        ]);

        $principal = $validated['property_price'] - $validated['down_payment'];

        if ($principal <= 0) {
            return response()->json(['data' => [
                'principal'         => 0,
                'monthly_payment'   => 0,
                'total_interest'    => 0,
                'total_cost'        => 0,
                'dti'               => null,
            ]]);
        }

        $termMonths    = $validated['term_years'] * 12;
        $monthly       = Amortization::monthlyPayment($principal, $validated['annual_rate_pct'], $termMonths);
        $totalInterest = Amortization::totalInterest($principal, $validated['annual_rate_pct'], $termMonths);

        $dti = null;
        if (!empty($validated['monthly_income'])) {
            $dti = DTIEngine::evaluate(
                $monthly,
                $validated['existing_debts'] ?? 0,
                $validated['monthly_income'],
                $validated['annual_rate_pct'],
                $termMonths,
            );
        }

        return response()->json(['data' => [
            'principal'       => round($principal, 2),
            'monthly_payment' => round($monthly, 2),
            'total_interest'  => round($totalInterest, 2),
            'total_cost'      => round($principal + $totalInterest, 2),
            'dti'             => $dti,
        ]]);
    }

    public function pagibig(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'property_value'          => 'required|numeric|min:0',
            'gross_monthly_income'    => 'required|numeric|min:0',
            'borrower_age'            => 'required|integer|min:18|max:74',
            'is_pagibig_member'       => 'required|boolean',
            'contribution_months'     => 'required|integer|min:0',
            'requested_loan'          => 'nullable|numeric|min:0',
            'requested_term_years'    => 'nullable|integer|min:1|max:30',
        ]);

        $result = PagIBIG::simulate(
            $validated['property_value'],
            $validated['gross_monthly_income'],
            $validated['borrower_age'],
            $validated['is_pagibig_member'],
            $validated['contribution_months'],
            $validated['requested_loan'] ?? 0,
            $validated['requested_term_years'] ?? 30,
        );

        return response()->json(['data' => $result]);
    }

    public function bank(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'loan_amount'      => 'required|numeric|min:0',
            'term_years'       => 'required|integer|min:1|max:30',
            'bank'             => 'nullable|string|in:bpi,bdo,metrobank,security_bank,rcbc,pnb',
            'teaser_rate_pct'  => 'required_without:bank|nullable|numeric|min:0|max:30',
            'teaser_years'     => 'required_without:bank|nullable|integer|min:1|max:10',
            'repriced_rate_pct'=> 'required_without:bank|nullable|numeric|min:0|max:30',
        ]);

        if (!empty($validated['bank'])) {
            $result = BankFinancing::simulatePreset(
                $validated['loan_amount'],
                $validated['term_years'],
                $validated['bank'],
            );
        } else {
            $result = BankFinancing::simulate(
                $validated['loan_amount'],
                $validated['term_years'],
                $validated['teaser_rate_pct'],
                $validated['teaser_years'],
                $validated['repriced_rate_pct'],
            );
        }

        return response()->json(['data' => $result]);
    }

    public function dti(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'proposed_monthly_payment' => 'required|numeric|min:0',
            'existing_obligations'     => 'required|numeric|min:0',
            'gross_monthly_income'     => 'required|numeric|min:1',
            'annual_rate_pct'          => 'required|numeric|min:0.1|max:30',
            'term_months'              => 'required|integer|min:12|max:420',
        ]);

        $result = DTIEngine::evaluate(
            $validated['proposed_monthly_payment'],
            $validated['existing_obligations'],
            $validated['gross_monthly_income'],
            $validated['annual_rate_pct'],
            $validated['term_months'],
        );

        return response()->json(['data' => $result]);
    }

    public function hiddenCosts(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'purchase_price'       => 'required|numeric|min:0',
            'loan_amount'          => 'nullable|numeric|min:0',
            'is_city'              => 'nullable|boolean',
            'buyer_pays_broker'    => 'nullable|boolean',
            'condo_assoc_move_in'  => 'nullable|numeric|min:0',
            'moving_cost'          => 'nullable|numeric|min:0',
        ]);

        $result = HiddenCosts::breakdown(
            $validated['purchase_price'],
            $validated['loan_amount'] ?? 0,
            $validated['is_city'] ?? true,
            $validated['buyer_pays_broker'] ?? false,
            $validated['condo_assoc_move_in'] ?? 0,
            $validated['moving_cost'] ?? 25_000,
        );

        return response()->json(['data' => $result]);
    }

    public function stressTest(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'principal'          => 'required|numeric|min:0',
            'annual_rate_pct'    => 'required|numeric|min:0.1|max:30',
            'term_years'         => 'required|integer|min:1|max:35',
            'gross_monthly_income' => 'required|numeric|min:1',
            'existing_debts'     => 'nullable|numeric|min:0',
            'is_ofw'             => 'nullable|boolean',
        ]);

        $principal   = $validated['principal'];
        $rate        = $validated['annual_rate_pct'];
        $termMonths  = $validated['term_years'] * 12;
        $income      = $validated['gross_monthly_income'];
        $debts       = $validated['existing_debts'] ?? 0;
        $isOfw       = $validated['is_ofw'] ?? false;

        $baseline = Amortization::monthlyPayment($principal, $rate, $termMonths);

        // +200 bps rate shock
        $rateShock        = Amortization::monthlyPayment($principal, $rate + 2.0, $termMonths);
        $rateShockDti     = DTIEngine::evaluate($rateShock, $debts, $income, $rate + 2.0, $termMonths);

        // 20% income drop (same payment, worse DTI)
        $incomeDrop       = $income * 0.80;
        $incomeDropDti    = DTIEngine::evaluate($baseline, $debts, $incomeDrop, $rate, $termMonths);

        $scenarios = [
            'baseline' => [
                'label'           => 'Baseline',
                'monthly_payment' => round($baseline, 2),
                'dti'             => DTIEngine::evaluate($baseline, $debts, $income, $rate, $termMonths),
            ],
            'rate_shock_200bps' => [
                'label'           => '+2% rate shock',
                'monthly_payment' => round($rateShock, 2),
                'payment_increase'=> round($rateShock - $baseline, 2),
                'dti'             => $rateShockDti,
            ],
            'income_drop_20pct' => [
                'label'           => '20% income drop',
                'monthly_payment' => round($baseline, 2),
                'income_used'     => round($incomeDrop, 2),
                'dti'             => $incomeDropDti,
            ],
        ];

        if ($isOfw) {
            // 10% peso depreciation → 10% effective income reduction in peso
            $fxIncome   = $income * 0.90;
            $fxDti      = DTIEngine::evaluate($baseline, $debts, $fxIncome, $rate, $termMonths);
            $scenarios['ofw_fx_10pct'] = [
                'label'        => '10% peso depreciation (OFW)',
                'monthly_payment' => round($baseline, 2),
                'income_used'  => round($fxIncome, 2),
                'dti'          => $fxDti,
            ];
        }

        return response()->json(['data' => ['scenarios' => $scenarios]]);
    }
}
