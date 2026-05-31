import { z } from "zod"

export const LoanType = z.enum(["pagibig", "bank", "developer_inhouse", "cash"])
export type LoanType = z.infer<typeof LoanType>

export const BankPreset = z.enum(["bpi", "bdo", "metrobank", "security_bank", "rcbc", "pnb", "custom"])
export type BankPreset = z.infer<typeof BankPreset>

export const AmortizationRow = z.object({
  month: z.number().int(),
  payment: z.number(),
  principal: z.number(),
  interest: z.number(),
  balance: z.number(),
})
export type AmortizationRow = z.infer<typeof AmortizationRow>

export const AmortizationResult = z.object({
  monthly_payment: z.number(),
  total_payment: z.number(),
  total_interest: z.number(),
  schedule: z.array(AmortizationRow),
})
export type AmortizationResult = z.infer<typeof AmortizationResult>

export const PagIBIGSimInput = z.object({
  property_price_php: z.number().positive(),
  down_payment_pct: z.number().min(0).max(50).default(10),
  term_years: z.number().int().min(1).max(30).default(25),
  borrower_age: z.number().int().min(18).max(65),
})
export type PagIBIGSimInput = z.infer<typeof PagIBIGSimInput>

export const PagIBIGSimResult = z.object({
  is_eligible: z.boolean(),
  ineligibility_reason: z.string().optional(),
  loan_amount: z.number(),
  applicable_rate_pct: z.number(),
  effective_term_years: z.number(),
  monthly_amortization: z.number(),
  max_maturity_age: z.number(),
})
export type PagIBIGSimResult = z.infer<typeof PagIBIGSimResult>

export const BankSimInput = z.object({
  loan_amount_php: z.number().positive(),
  term_years: z.number().int().min(1).max(30),
  bank: BankPreset,
  teaser_rate_pct: z.number().positive().optional(),
  teaser_years: z.number().int().min(0).max(10).default(3),
  repriced_rate_pct: z.number().positive().optional(),
})
export type BankSimInput = z.infer<typeof BankSimInput>

export const BankSimResult = z.object({
  bank: BankPreset,
  loan_amount: z.number(),
  teaser_rate_pct: z.number(),
  repriced_rate_pct: z.number(),
  monthly_payment_teaser: z.number(),
  monthly_payment_repriced: z.number(),
  total_interest: z.number(),
  five_year_summary: z.object({
    total_paid: z.number(),
    principal_paid: z.number(),
    interest_paid: z.number(),
    remaining_balance: z.number(),
  }),
})
export type BankSimResult = z.infer<typeof BankSimResult>

export const DTIResult = z.object({
  dti_ratio: z.number(),
  dti_pct: z.number(),
  status: z.enum(["safe", "caution", "warning", "critical"]),
  message: z.string(),
  recommended_max_loan: z.number(),
})
export type DTIResult = z.infer<typeof DTIResult>

export const HiddenCostBreakdown = z.object({
  documentary_stamp_tax: z.number(),
  transfer_tax: z.number(),
  registration_fee: z.number(),
  notarial_fee: z.number(),
  broker_commission: z.number(),
  moving_costs: z.number(),
  association_move_in: z.number(),
  total: z.number(),
})
export type HiddenCostBreakdown = z.infer<typeof HiddenCostBreakdown>

export const DownPaymentPlan = z.object({
  property_price_php: z.number(),
  down_payment_pct: z.number(),
  down_payment_amount: z.number(),
  reservation_fee: z.number(),
  spot_dp_amount: z.number(),
  monthly_equity_php: z.number(),
  equity_months: z.number().int(),
  cash_at_close: z.number(),
  total_cash_required: z.number(),
})
export type DownPaymentPlan = z.infer<typeof DownPaymentPlan>

export const StressTestScenario = z.object({
  name: z.string(),
  monthly_payment_after: z.number(),
  dti_after: z.number(),
  status: z.enum(["manageable", "strained", "critical"]),
  description: z.string(),
})
export type StressTestScenario = z.infer<typeof StressTestScenario>

export const StressTestResult = z.object({
  baseline_monthly: z.number(),
  baseline_dti: z.number(),
  scenarios: z.array(StressTestScenario),
  overall_risk: z.enum(["low", "moderate", "high", "critical"]),
  recommendation: z.string(),
})
export type StressTestResult = z.infer<typeof StressTestResult>

export const FinancialSimulation = z.object({
  input: z.object({
    property_price_php: z.number(),
    loan_type: LoanType,
    term_years: z.number().int(),
    monthly_take_home_php: z.number(),
    existing_obligations_php: z.number(),
    borrower_age: z.number().int().optional(),
    bank: BankPreset.optional(),
  }),
  pagibig: PagIBIGSimResult.optional(),
  bank: BankSimResult.optional(),
  dti: DTIResult,
  hidden_costs: HiddenCostBreakdown,
  down_payment_plan: DownPaymentPlan,
  stress_test: StressTestResult,
  computed_at: z.string().datetime(),
})
export type FinancialSimulation = z.infer<typeof FinancialSimulation>
