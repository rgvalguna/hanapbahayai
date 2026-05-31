import type { Metadata } from "next"
import { LoanSimulator } from "@/components/financial/LoanSimulator"
import { DTIGauge } from "@/components/financial/DTIGauge"

export const metadata: Metadata = { title: "Loan Simulator" }

export default function FinancialPage() {
  return (
    <div>
      <div className="mb-6">
        <h1 className="text-2xl font-semibold text-gray-900">Loan Simulator</h1>
        <p className="mt-1 text-sm text-gray-500">
          Model Pag-IBIG and bank financing scenarios. Figures are illustrative — not a loan offer.
        </p>
      </div>

      <div className="grid gap-8 lg:grid-cols-[1fr,400px]">
        {/* Left: explainer + DTI context */}
        <div className="space-y-6">
          <div className="rounded-2xl border border-[--color-border] bg-white p-6">
            <h2 className="mb-1 text-base font-semibold text-gray-900">
              Understanding your DTI
            </h2>
            <p className="mb-4 text-sm text-gray-600">
              Debt-to-Income (DTI) ratio measures how much of your take-home pay goes toward debt
              repayments. Philippine banks and Pag-IBIG generally cap loanable amounts at 30–35% DTI.
            </p>
            <div className="grid gap-4 sm:grid-cols-3">
              {[
                { label: "Safe", range: "≤ 30%", desc: "Comfortable buffer for savings and emergencies", color: "bg-green-100 border-green-200 text-green-800" },
                { label: "Caution", range: "30–35%", desc: "Tight but lender-accepted; limited savings room", color: "bg-amber-100 border-amber-200 text-amber-800" },
                { label: "Risk", range: "> 35%", desc: "May be declined or requires co-borrower", color: "bg-red-100 border-red-200 text-red-800" },
              ].map(({ label, range, desc, color }) => (
                <div key={label} className={`rounded-xl border p-4 ${color}`}>
                  <div className="text-sm font-bold">{label}</div>
                  <div className="text-lg font-black tabular-nums">{range}</div>
                  <div className="mt-1 text-xs opacity-80">{desc}</div>
                </div>
              ))}
            </div>
          </div>

          <div className="rounded-2xl border border-[--color-border] bg-white p-6">
            <h2 className="mb-4 text-base font-semibold text-gray-900">
              Philippine financing options
            </h2>
            <div className="space-y-4">
              {[
                {
                  name: "Pag-IBIG Fund",
                  rate: "6.25–10.0%",
                  max: "PHP 6,000,000",
                  term: "Up to 30 years",
                  notes: "Best rates for regular Pag-IBIG members. Income doc required. Max loan = 75 − age at maturity.",
                },
                {
                  name: "Bank Financing",
                  rate: "6.25–7.5%",
                  max: "No cap (credit-based)",
                  term: "5–20 years (typical)",
                  notes: "Teaser rates reset after 1–5 years. Verify re-pricing terms before signing.",
                },
                {
                  name: "In-house Financing",
                  rate: "12–18%",
                  max: "Varies by developer",
                  term: "3–15 years",
                  notes: "Higher rates but looser income requirements. Often used for pre-selling.",
                },
              ].map((opt) => (
                <div key={opt.name} className="rounded-xl border border-[--color-border] p-4">
                  <div className="mb-2 text-sm font-semibold text-gray-900">{opt.name}</div>
                  <div className="grid grid-cols-3 gap-2 text-xs">
                    <div>
                      <div className="text-gray-400">Rate</div>
                      <div className="font-medium text-gray-700">{opt.rate}</div>
                    </div>
                    <div>
                      <div className="text-gray-400">Max loan</div>
                      <div className="font-medium text-gray-700">{opt.max}</div>
                    </div>
                    <div>
                      <div className="text-gray-400">Term</div>
                      <div className="font-medium text-gray-700">{opt.term}</div>
                    </div>
                  </div>
                  <p className="mt-2 text-xs text-gray-500">{opt.notes}</p>
                </div>
              ))}
            </div>
          </div>
        </div>

        {/* Right: simulator */}
        <div className="lg:sticky lg:top-6 lg:self-start">
          <LoanSimulator listingPrice={3_500_000} />
        </div>
      </div>
    </div>
  )
}
