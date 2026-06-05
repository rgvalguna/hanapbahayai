"use client"

import { useState } from "react"
import { useQuery } from "@tanstack/react-query"
import { formatPHP } from "@/lib/utils"
import { DTIGauge } from "./DTIGauge"
import { Calculator, ChevronDown, ChevronUp, Loader2 } from "lucide-react"
import { simulatePagIBIG } from "@/lib/api/financial"

interface LoanSimulatorProps {
  listingPrice: number
  listingSlug?: string
}

type FinancingMode = "pagibig" | "bank"

const BANK_RATES: { label: string; rate: number }[] = [
  { label: "BPI (6.5%)", rate: 6.5 },
  { label: "BDO (6.75%)", rate: 6.75 },
  { label: "Metrobank (6.75%)", rate: 6.75 },
  { label: "Security Bank (6.25%)", rate: 6.25 },
]

function pmt(principal: number, annualRate: number, termYears: number): number {
  if (annualRate === 0) return principal / (termYears * 12)
  const r = annualRate / 100 / 12
  const n = termYears * 12
  return (principal * r * Math.pow(1 + r, n)) / (Math.pow(1 + r, n) - 1)
}

export function LoanSimulator({ listingPrice, listingSlug: _slug }: LoanSimulatorProps) {
  const [mode, setMode] = useState<FinancingMode>("pagibig")
  const [downPct, setDownPct] = useState(20)
  const [termYears, setTermYears] = useState(20)
  const [bankRateIdx, setBankRateIdx] = useState(0)
  const [monthlyIncome, setMonthlyIncome] = useState(60_000)
  const [expanded, setExpanded] = useState(false)

  const downPayment = (listingPrice * downPct) / 100
  const loanAmount = listingPrice - downPayment

  // Pag-IBIG: fetch real eligibility + rate from backend
  const pagibigQuery = useQuery({
    queryKey: ["pagibig-sim", listingPrice, downPct, termYears, monthlyIncome],
    queryFn: () =>
      simulatePagIBIG({
        property_value: listingPrice,
        gross_monthly_income: monthlyIncome,
        age: 30, // conservative default — user can update via profile
        is_pagibig_member: true,
        requested_loan: loanAmount,
        term_years: termYears,
      }),
    enabled: mode === "pagibig",
    staleTime: 60_000,
    retry: false,
  })

  // Derive display values — backend for Pag-IBIG, client-side PMT for bank
  const bankRate = BANK_RATES[bankRateIdx]?.rate ?? 6.5
  const bankMonthly = pmt(loanAmount, bankRate, termYears)

  const pagibigData = pagibigQuery.data
  const monthly = mode === "pagibig" ? (pagibigData?.monthly_payment ?? pmt(loanAmount, 6.375, termYears)) : bankMonthly
  const rate = mode === "pagibig" ? (pagibigData?.annual_rate_pct ?? 6.375) : bankRate
  const totalInterest = mode === "pagibig" ? (pagibigData?.total_interest ?? monthly * termYears * 12 - loanAmount) : bankMonthly * termYears * 12 - loanAmount
  const totalCost = listingPrice + totalInterest
  const closingCosts = listingPrice * 0.065
  const dtiRatio = monthlyIncome > 0 ? monthly / monthlyIncome : 0

  const inputCls =
    "w-full rounded-lg border border-[--color-border] px-3 py-2 text-sm focus:border-[--color-brand-500] focus:outline-none focus:ring-2 focus:ring-[--color-brand-500]/20"
  const labelCls = "mb-1 block text-xs font-medium text-gray-600"

  return (
    <div className="rounded-2xl border border-[--color-border] bg-white">
      {/* Header */}
      <div className="flex items-center justify-between border-b border-[--color-border] px-5 py-4">
        <div className="flex items-center gap-2">
          <Calculator className="h-4 w-4 text-[--color-brand-600]" />
          <h2 className="text-sm font-semibold text-gray-900">Loan Simulator</h2>
        </div>
        <div className="flex rounded-lg border border-[--color-border] p-0.5 text-xs">
          <button
            onClick={() => setMode("pagibig")}
            className={`rounded-md px-2.5 py-1 font-medium transition ${
              mode === "pagibig"
                ? "bg-[--color-brand-600] text-white"
                : "text-gray-500 hover:text-gray-700"
            }`}
          >
            Pag-IBIG
          </button>
          <button
            onClick={() => setMode("bank")}
            className={`rounded-md px-2.5 py-1 font-medium transition ${
              mode === "bank"
                ? "bg-[--color-brand-600] text-white"
                : "text-gray-500 hover:text-gray-700"
            }`}
          >
            Bank
          </button>
        </div>
      </div>

      {/* Inputs */}
      <div className="space-y-4 p-5">
        {/* Down payment */}
        <div>
          <div className="mb-1 flex items-center justify-between">
            <label className={labelCls}>Down payment</label>
            <span className="text-xs font-semibold text-gray-700">{downPct}%</span>
          </div>
          <input
            type="range"
            min={10}
            max={50}
            step={5}
            value={downPct}
            onChange={(e) => setDownPct(Number(e.target.value))}
            className="w-full accent-[--color-brand-600]"
          />
          <div className="mt-0.5 flex justify-between text-[10px] text-gray-400">
            <span>10%</span>
            <span className="font-medium text-gray-600">{formatPHP(downPayment)}</span>
            <span>50%</span>
          </div>
        </div>

        {/* Term */}
        <div>
          <div className="mb-1 flex items-center justify-between">
            <label className={labelCls}>Loan term</label>
            <span className="text-xs font-semibold text-gray-700">{termYears} yrs</span>
          </div>
          <input
            type="range"
            min={5}
            max={30}
            step={5}
            value={termYears}
            onChange={(e) => setTermYears(Number(e.target.value))}
            className="w-full accent-[--color-brand-600]"
          />
          <div className="mt-0.5 flex justify-between text-[10px] text-gray-400">
            <span>5 yrs</span>
            <span>30 yrs</span>
          </div>
        </div>

        {/* Bank selector */}
        {mode === "bank" && (
          <div>
            <label className={labelCls}>Bank</label>
            <select
              value={bankRateIdx}
              onChange={(e) => setBankRateIdx(Number(e.target.value))}
              className={inputCls}
            >
              {BANK_RATES.map((b, i) => (
                <option key={b.label} value={i}>
                  {b.label}
                </option>
              ))}
            </select>
          </div>
        )}

        {/* Pag-IBIG ineligibility banner */}
        {mode === "pagibig" && pagibigData && !pagibigData.eligible && (
          <div className="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800">
            <strong>Not eligible for Pag-IBIG:</strong> {pagibigData.ineligibility_reason ?? "Property value or income threshold not met."}
            <br />
            <span className="text-amber-600">Showing illustrative rates below.</span>
          </div>
        )}

        {/* Monthly income for DTI */}
        <div>
          <label className={labelCls}>Your take-home income / mo</label>
          <input
            type="number"
            value={monthlyIncome}
            onChange={(e) => setMonthlyIncome(Number(e.target.value))}
            min={0}
            step={5000}
            className={inputCls}
          />
        </div>
      </div>

      {/* Result */}
      <div className="border-t border-[--color-border] bg-[--color-brand-50] px-5 py-4">
        <div className="mb-3 flex items-baseline justify-between">
          <span className="text-xs text-gray-500">Est. monthly payment</span>
          {mode === "pagibig" && pagibigQuery.isFetching ? (
            <Loader2 className="h-5 w-5 animate-spin text-[--color-brand-500]" />
          ) : (
            <span className="text-2xl font-bold text-gray-900 tabular-nums">
              {formatPHP(monthly)}
            </span>
          )}
        </div>

        <DTIGauge ratio={dtiRatio} className="mb-4" />

        {/* Expand toggle */}
        <button
          onClick={() => setExpanded((v) => !v)}
          className="flex w-full items-center justify-between text-xs font-medium text-[--color-brand-600] hover:text-[--color-brand-700]"
        >
          Full breakdown
          {expanded ? <ChevronUp className="h-3.5 w-3.5" /> : <ChevronDown className="h-3.5 w-3.5" />}
        </button>

        {expanded && (
          <div className="mt-3 space-y-1.5 border-t border-[--color-brand-100] pt-3">
            {[
              ["Loan amount", formatPHP(loanAmount)],
              ["Interest rate", `${rate.toFixed(2)}%`],
              ["Total interest", formatPHP(totalInterest)],
              ["Total cost of loan", formatPHP(totalCost)],
              ["Est. closing costs (~6.5%)", formatPHP(closingCosts)],
              ["Cash needed upfront", formatPHP(downPayment + closingCosts)],
            ].map(([label, value]) => (
              <div key={label} className="flex justify-between text-xs">
                <span className="text-gray-500">{label}</span>
                <span className="font-medium text-gray-800 tabular-nums">{value}</span>
              </div>
            ))}
          </div>
        )}
      </div>

      <p className="px-5 py-3 text-[10px] text-gray-400">
        Illustrative only — not a loan offer. Rates and terms vary by lender and credit profile.
      </p>
    </div>
  )
}

