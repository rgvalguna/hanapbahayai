import { apiFetch } from "./client"

// ── Pag-IBIG ──────────────────────────────────────────────────────────────────

export interface PagIBIGInput {
  property_value: number
  gross_monthly_income: number
  age: number
  is_pagibig_member: boolean
  contribution_months?: number
  requested_loan?: number
  term_years?: number
}

export interface PagIBIGResult {
  eligible: boolean
  ineligibility_reason?: string
  loan_amount: number
  term_years: number
  annual_rate_pct: number
  monthly_payment: number
  total_interest: number
  total_payments: number
  ltv_pct: number
}

export async function simulatePagIBIG(input: PagIBIGInput): Promise<PagIBIGResult> {
  const res = await apiFetch("/v1/financial/pagibig", {
    method: "POST",
    body: JSON.stringify(input),
  })
  const data = await res.json() as { data: PagIBIGResult }
  return data.data
}

// ── Bank Financing ─────────────────────────────────────────────────────────────

export interface BankInput {
  property_value: number
  loan_amount: number
  term_years: number
  bank?: string
  teaser_rate_pct?: number
  repriced_rate_pct?: number
  teaser_years?: number
}

export interface BankResult {
  bank: string
  loan_amount: number
  term_years: number
  teaser_rate_pct: number
  repriced_rate_pct: number
  monthly_teaser: number
  monthly_repriced: number
  total_interest: number
  balance_at_repricing: number
}

export async function simulateBank(input: BankInput): Promise<BankResult> {
  const res = await apiFetch("/v1/financial/bank", {
    method: "POST",
    body: JSON.stringify(input),
  })
  const data = await res.json() as { data: BankResult }
  return data.data
}

// ── DTI ───────────────────────────────────────────────────────────────────────

export interface DTIInput {
  gross_monthly_income: number
  proposed_monthly_payment: number
  existing_monthly_obligations?: number
}

export interface DTIResult {
  dti_ratio: number
  dti_pct: number
  status: "safe" | "caution" | "warning" | "critical"
  status_message: string
  recommended_max_loan: number
  recommended_max_monthly: number
}

export async function evaluateDTI(input: DTIInput): Promise<DTIResult> {
  const res = await apiFetch("/v1/financial/dti", {
    method: "POST",
    body: JSON.stringify(input),
  })
  const data = await res.json() as { data: DTIResult }
  return data.data
}

// ── Hidden Costs ──────────────────────────────────────────────────────────────

export interface HiddenCostsInput {
  property_value: number
  loan_amount: number
  is_city?: boolean
  is_condo?: boolean
  broker_commission_pct?: number
}

export interface HiddenCostsResult {
  documentary_stamp_tax: number
  transfer_tax: number
  registration_fee: number
  notarial_fee: number
  broker_commission: number
  move_in_deposit: number
  total_one_time: number
  total_recurring_monthly: number
}

export async function calculateHiddenCosts(input: HiddenCostsInput): Promise<HiddenCostsResult> {
  const res = await apiFetch("/v1/financial/hidden-costs", {
    method: "POST",
    body: JSON.stringify(input),
  })
  const data = await res.json() as { data: HiddenCostsResult }
  return data.data
}
