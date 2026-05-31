<?php

namespace App\Modules\AI\Tools;

use App\Models\Listing;
use App\Models\User;
use App\Modules\Financial\Amortization;
use App\Modules\Financial\BankFinancing;
use App\Modules\Financial\PagIBIG;

class SimulateLoan
{
    public function execute(array $input, ?User $user = null): array
    {
        $listingId     = $input['listing_id'] ?? null;
        $propertyPrice = isset($input['property_price']) ? (float) $input['property_price'] : null;

        if ($listingId && !$propertyPrice) {
            $listing = Listing::find($listingId);
            if (!$listing) {
                return ['error' => "Listing {$listingId} not found"];
            }
            $propertyPrice = (float) $listing->price_php;
        }

        if (!$propertyPrice) {
            return ['error' => 'Either listing_id or property_price is required'];
        }

        $downPayment = isset($input['down_payment'])
            ? (float) $input['down_payment']
            : $propertyPrice * 0.20;

        $loanAmount = $propertyPrice - $downPayment;
        $termYears  = (int) ($input['term_years'] ?? 20);
        $modes      = $input['modes'] ?? ['pagibig', 'bank_bpi', 'bank_bdo'];

        $results = [
            'property_price' => $propertyPrice,
            'down_payment'   => $downPayment,
            'loan_amount'    => $loanAmount,
            'term_years'     => $termYears,
            'simulations'    => [],
        ];

        // Resolve user finance data for Pag-IBIG eligibility
        $grossIncome    = 0;
        $borrowerAge    = 35;
        $pagibigMember  = false;
        $contributions  = 0;

        if ($user) {
            $finances = $user->currentFinances;
            if ($finances) {
                $grossIncome   = (float) $finances->gross_monthly_income_php;
                $pagibigMember = (bool) $finances->pagibig_member;
                $contributions = (int) ($finances->pagibig_contributions_months ?? 0);
            }
            $borrowerAge = $user->created_at ? now()->diffInYears($user->created_at) + 22 : 35;
            $borrowerAge = max(18, min(65, $borrowerAge));
        }

        foreach ($modes as $mode) {
            if ($mode === 'pagibig') {
                $sim = PagIBIG::simulate(
                    propertyValue:          $propertyPrice,
                    grossMonthlyIncome:     $grossIncome ?: 50000,
                    borrowerAge:            $borrowerAge,
                    pagibigCurrentMember:   $pagibigMember,
                    contributionMonths:     $contributions,
                    requestedLoan:          $loanAmount,
                    requestedTermYears:     $termYears,
                );
                $results['simulations']['pagibig'] = $sim;
                continue;
            }

            // Bank modes: bank_bpi, bank_bdo, bank_metrobank, bank_security_bank, bank_rcbc, bank_pnb
            if (str_starts_with($mode, 'bank_')) {
                $bankCode = substr($mode, 5);
                try {
                    $sim = BankFinancing::simulatePreset($loanAmount, $termYears, $bankCode);
                    $results['simulations'][$mode] = $sim;
                } catch (\InvalidArgumentException $e) {
                    $results['simulations'][$mode] = ['error' => $e->getMessage()];
                }
                continue;
            }

            if ($mode === 'custom') {
                $rate = (float) ($input['custom_annual_rate_pct'] ?? 7.0);
                $monthly = Amortization::monthlyPayment($loanAmount, $rate, $termYears * 12);
                $totalInterest = Amortization::totalInterest($loanAmount, $rate, $termYears * 12);
                $results['simulations']['custom'] = [
                    'annual_rate_pct'  => $rate,
                    'monthly_payment'  => round($monthly, 2),
                    'total_interest'   => round($totalInterest, 2),
                    'total_cost'       => round($loanAmount + $totalInterest, 2),
                ];
            }
        }

        // Hidden costs
        if ($input['include_hidden_costs'] ?? true) {
            $results['hidden_costs'] = $this->estimateHiddenCosts($propertyPrice);
        }

        // Stress test
        if ($input['include_stress_test'] ?? false) {
            $results['stress_test'] = $this->stressTest($loanAmount, $termYears, $grossIncome, $user?->is_ofw ?? false);
        }

        // DTI context
        if ($grossIncome > 0 && !empty($results['simulations'])) {
            $takeHome = $grossIncome * 0.85;
            $results['dti_context'] = [
                'estimated_take_home'  => round($takeHome, 2),
                'max_safe_amort_30pct' => round($takeHome * 0.30, 2),
                'max_amort_35pct'      => round($takeHome * 0.35, 2),
                'caution_threshold_40pct' => round($takeHome * 0.40, 2),
            ];
        }

        return $results;
    }

    private function estimateHiddenCosts(float $price): array
    {
        return [
            'documentary_stamp_tax'  => round($price * 0.015, 2),
            'transfer_tax'           => round($price * 0.006, 2),
            'registration_fee'       => round($price * 0.0025, 2),
            'notarial_fee'           => round($price * 0.001, 2),
            'broker_commission'      => round($price * 0.05, 2),
            'moving_estimate'        => 30000,
            'total_estimated'        => round($price * (0.015 + 0.006 + 0.0025 + 0.001 + 0.05) + 30000, 2),
            'note'                   => 'Transfer tax varies by LGU (0.5–0.75%). These are estimates — verify with your lawyer.',
        ];
    }

    private function stressTest(float $loanAmount, int $termYears, float $grossIncome, bool $isOfw): array
    {
        $baseRate   = 7.0;
        $shockRate  = $baseRate + 2.0; // +200 bps
        $baseMonthly  = Amortization::monthlyPayment($loanAmount, $baseRate,  $termYears * 12);
        $shockMonthly = Amortization::monthlyPayment($loanAmount, $shockRate, $termYears * 12);
        $incomeDropIncome = $grossIncome * 0.80;

        $result = [
            'rate_shock_200bps' => [
                'new_rate_pct'     => $shockRate,
                'new_monthly'      => round($shockMonthly, 2),
                'delta_monthly'    => round($shockMonthly - $baseMonthly, 2),
            ],
            'income_drop_20pct' => [
                'stressed_income'  => round($incomeDropIncome, 2),
                'take_home_est'    => round($incomeDropIncome * 0.85, 2),
                'base_dti_on_drop' => $incomeDropIncome > 0
                    ? round($baseMonthly / ($incomeDropIncome * 0.85) * 100, 1)
                    : null,
            ],
        ];

        if ($isOfw) {
            $result['ofw_fx_shock_10pct'] = [
                'description'     => '10% peso appreciation reduces OFW remittance value',
                'effective_income_drop_pct' => 10,
                'note'            => 'OFW borrowers should carry at least 6 months of amortization reserve.',
            ];
        }

        return $result;
    }
}
