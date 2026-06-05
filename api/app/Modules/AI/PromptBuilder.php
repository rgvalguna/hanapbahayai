<?php

namespace App\Modules\AI;

use App\Models\User;

class PromptBuilder
{
    private string $basePrompt;

    public function __construct()
    {
        $path = base_path('prompts/system_prompt.md');
        $this->basePrompt = file_exists($path) ? file_get_contents($path) : $this->fallbackPrompt();
    }

    /**
     * Build the system prompt array for the Anthropic API.
     *
     * Only the static base prompt (which never changes per-user) is marked with
     * cache_control so Anthropic can cache it across requests — fixes B5.
     * The per-user profile block changes on every turn and must NOT be cached;
     * adding cache_control to it would yield ~0% hit rate while incurring the
     * cache-write surcharge on every request.
     */
    public function build(User $user): array
    {
        $profileBlock = $this->renderProfileBlock($user);

        $staticText = str_replace('{{PROFILE_BLOCK}}', '', $this->basePrompt);

        return [
            [
                'type'          => 'text',
                'text'          => trim($staticText),
                'cache_control' => ['type' => 'ephemeral'],  // stable — cache this
            ],
            [
                'type' => 'text',
                'text' => $profileBlock,
                // No cache_control — per-user / per-turn, never cache
            ],
        ];
    }

    private function renderProfileBlock(User $user): string
    {
        $profile  = $user->profile;
        $finances = $user->currentFinances;
        $prefs    = optional($user->preferences());

        $lines = ['## Current User Profile'];

        // Identity
        $lines[] = sprintf('Name: %s', $user->name ?? 'Unknown');
        $lines[] = sprintf('OFW: %s', $user->is_ofw ? 'Yes' : 'No');

        // Archetype & modifiers
        if ($profile) {
            $lines[] = sprintf('Archetype: %s', $profile->archetype ?? 'Unknown');
            $modifiers = $profile->modifiers ?? [];
            if ($modifiers) {
                $lines[] = 'Modifiers: ' . implode(', ', $modifiers);
            }
            $lines[] = sprintf('Risk Tolerance: %s', $profile->risk_tolerance ?? 'Balanced');
            $lines[] = sprintf('Purchase Purpose: %s', $profile->purchase_purpose ?? 'residence');
            if ($profile->family_size) {
                $lines[] = sprintf('Family Size: %d (children: %d)', $profile->family_size, $profile->num_children ?? 0);
            }
        }

        // Finances
        if ($finances) {
            $lines[] = '';
            $lines[] = '### Finances';
            $lines[] = sprintf('Gross Monthly Income: PHP %s', number_format($finances->gross_monthly_income_php, 0));

            $currency = $finances->currency ?? 'PHP';
            if ($currency !== 'PHP') {
                $lines[] = sprintf('Income Currency: %s (remittance)', $currency);
            }

            $lines[] = sprintf('Employment Type: %s', $finances->employment_type ?? 'employed');
            $lines[] = sprintf('Monthly Obligations: PHP %s', number_format($finances->monthly_obligations_php ?? 0, 0));
            $lines[] = sprintf('Available Down Payment: PHP %s', number_format($finances->available_down_payment_php ?? 0, 0));
            $lines[] = sprintf('Monthly Savings: PHP %s', number_format($finances->monthly_savings_php ?? 0, 0));
            $lines[] = sprintf('Pag-IBIG Member: %s', $finances->pagibig_member ? 'Yes' : 'No');

            if ($finances->has_co_borrower && $finances->co_borrower_income_php > 0) {
                $lines[] = sprintf('Co-borrower Income: PHP %s', number_format($finances->co_borrower_income_php, 0));

                $combined = $finances->gross_monthly_income_php + $finances->co_borrower_income_php;
                $lines[] = sprintf('Combined Household Income: PHP %s', number_format($combined, 0));
            }

            // Affordability context — gross-income basis is canonical (lender reality).
            // Take-home is shown as a sustainability overlay only, not as the gating number.
            $grossIncome = $finances->gross_monthly_income_php;
            $obligations = $finances->monthly_obligations_php ?? 0;

            // DTI gating (matches DTIEngine thresholds): 30% of gross
            $maxAmortGross = $grossIncome * 0.30;
            $lines[] = sprintf('Max Monthly Amortization — DTI gate (30%% of gross): PHP %s', number_format($maxAmortGross, 0));

            // Sustainability overlay: 30% of estimated take-home (advisory, not a hard cap)
            $takeHome       = $grossIncome * 0.85; // rough deduction estimate
            $maxAmortTakeHome = $takeHome * 0.30;
            $lines[] = sprintf('Estimated Take-Home: PHP %s', number_format($takeHome, 0));
            $lines[] = sprintf('Sustainability overlay (30%% of take-home, advisory only): PHP %s', number_format($maxAmortTakeHome, 0));
        }

        // Preferences (model may not exist yet)
        try {
            $prefModel = $user->preferences;
            if ($prefModel) {
                $lines[] = '';
                $lines[] = '### Preferences';
                if (!empty($prefModel->property_types)) {
                    $lines[] = 'Property Types: ' . implode(', ', $prefModel->property_types);
                }
                if (!empty($prefModel->preferred_lgus)) {
                    $lines[] = 'Preferred Areas: ' . implode(', ', $prefModel->preferred_lgus);
                }
                if (!empty($prefModel->must_haves)) {
                    $lines[] = 'Must-Haves: ' . implode(', ', $prefModel->must_haves);
                }
                if (!empty($prefModel->deal_breakers)) {
                    $lines[] = 'Deal-Breakers: ' . implode(', ', $prefModel->deal_breakers);
                }
            }
        } catch (\Exception) {
            // Preferences model/table may not exist in early migration states
        }

        return implode("\n", $lines);
    }

    private function fallbackPrompt(): string
    {
        return <<<'PROMPT'
You are HanapBahay AI, a fiduciary real estate advisor for the Philippine market.

{{PROFILE_BLOCK}}

Your primary duty is to the buyer's long-term financial stability, not to close transactions.

Core rules:
- Always call tools to get numbers; never invent prices or loan figures.
- Warn clearly when amortization exceeds 30% of take-home pay.
- Refuse to recommend a property that exceeds the user's stress-tested affordability.
- Explain all finance terms in plain English/Filipino.
- Surface trade-offs explicitly; never recommend without naming what is given up.
- When unsure, ask ONE clarifying question.
PROMPT;
    }
}
