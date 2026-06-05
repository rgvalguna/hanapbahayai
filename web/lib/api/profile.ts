import { apiFetch } from "./client"

export interface ProfilePayload {
  // Step 1: Identity
  purpose: "residence" | "investment" | "both"
  urgency: "asap" | "6months" | "1year" | "exploring"
  // Step 2: Finances
  monthly_gross: number
  monthly_takehome: number
  monthly_expenses: number
  existing_debts: number
  savings: number
  is_ofw: boolean
  target_amort_min: number
  target_amort_max: number
  // Step 3: Household
  family_size: number
  commute_mode: "car" | "transit" | "mixed"
  max_commute_minutes: number
  needs_school: boolean
  needs_hospital: boolean
  work_city: string
  // Step 4: Preferences
  property_types: string[]
  preferred_lgus: string[]
  risk_tolerance: "conservative" | "balanced" | "aggressive"
  must_haves: string[]
  deal_breakers: string[]
}

export async function saveProfile(payload: ProfilePayload): Promise<void> {
  await apiFetch("/v1/profile/onboarding", {
    method: "POST",
    body: JSON.stringify(payload),
  })
}

export interface FinancialSimulationInput {
  listing_price: number
  down_payment_pct: number
  loan_type: "pagibig" | "bank" | "inhouse"
  interest_rate_pct: number
  term_years: number
  monthly_income: number
  existing_monthly_debts: number
}

export interface FinancialSimulationOutput {
  monthly_amortization: number
  total_loan_amount: number
  total_interest: number
  total_payments: number
  dti_ratio: number
  dti_status: "safe" | "caution" | "risk"
  down_payment_php: number
  closing_costs_php: number
  total_upfront_php: number
  stress_test: {
    rate_shock_amort: number
    income_drop_dti: number
    ofw_fx_amort?: number
  }
}

export async function simulateLoan(
  input: FinancialSimulationInput,
): Promise<FinancialSimulationOutput> {
  const res = await apiFetch("/v1/financial/simulate", {
    method: "POST",
    body: JSON.stringify(input),
  })
  const data = await res.json() as { data: FinancialSimulationOutput }
  return data.data
}
