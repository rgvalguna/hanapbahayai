"use client"

import { useState } from "react"
import { useRouter } from "next/navigation"
import { MapPin, User, Wallet, Home, ChevronRight, ChevronLeft, Check } from "lucide-react"
import { cn } from "@/lib/utils"
import { apiFetch } from "@/lib/api/client"
import { useAuthStore } from "@/lib/store/auth"

// ─── Types ────────────────────────────────────────────────────────────────────

interface IdentityStep {
  purpose: "residence" | "investment" | "both" | ""
  urgency: "asap" | "6months" | "1year" | "exploring" | ""
}

interface FinanceStep {
  monthly_gross: string
  monthly_takehome: string
  monthly_expenses: string
  existing_debts: string
  savings: string
  is_ofw: boolean
  target_amort_min: string
  target_amort_max: string
}

interface HouseholdStep {
  family_size: string
  commute_mode: "car" | "transit" | "mixed" | ""
  max_commute_minutes: string
  needs_school: boolean
  needs_hospital: boolean
  work_city: string
}

interface PreferenceStep {
  property_types: string[]
  preferred_lgus: string[]
  risk_tolerance: "conservative" | "balanced" | "aggressive" | ""
  must_haves: string[]
  deal_breakers: string[]
}

const PROPERTY_TYPE_OPTIONS = [
  { value: "condo", label: "Condominium" },
  { value: "townhouse", label: "Townhouse" },
  { value: "house_lot", label: "House & Lot" },
  { value: "lot_only", label: "Lot Only" },
]

const MUST_HAVE_OPTIONS = [
  "Parking", "Swimming pool", "Gym", "Gated community",
  "Near public transport", "Pet-friendly", "Backup power", "Near school",
]

const DEAL_BREAKER_OPTIONS = [
  "No flooding history", "No active fault line", "No high-tension wires",
  "No informal settlers nearby", "Not pre-selling", "No in-house financing only",
]

// ─── Step components ──────────────────────────────────────────────────────────

function ChipGroup({
  options, selected, onChange, multi = true,
}: {
  options: { value: string; label: string }[]
  selected: string[]
  onChange: (val: string[]) => void
  multi?: boolean
}) {
  function toggle(v: string) {
    if (multi) {
      onChange(selected.includes(v) ? selected.filter((x) => x !== v) : [...selected, v])
    } else {
      onChange(selected.includes(v) ? [] : [v])
    }
  }
  return (
    <div className="flex flex-wrap gap-2">
      {options.map((opt) => (
        <button
          key={opt.value}
          type="button"
          onClick={() => toggle(opt.value)}
          className={cn(
            "rounded-full border px-3.5 py-1.5 text-sm transition",
            selected.includes(opt.value)
              ? "border-[--color-brand-500] bg-[--color-brand-50] text-[--color-brand-700] font-medium"
              : "border-[--color-border] text-gray-600 hover:border-gray-300",
          )}
        >
          {opt.label}
        </button>
      ))}
    </div>
  )
}

function FieldInput({
  label, id, type = "text", value, onChange, prefix, suffix, placeholder, hint,
}: {
  label: string; id: string; type?: string; value: string
  onChange: (v: string) => void; prefix?: string; suffix?: string
  placeholder?: string; hint?: string
}) {
  return (
    <div>
      <label htmlFor={id} className="mb-1.5 block text-sm font-medium text-gray-700">
        {label}
      </label>
      <div className="relative flex items-center">
        {prefix && (
          <span className="pointer-events-none absolute left-3 text-sm text-gray-400">{prefix}</span>
        )}
        <input
          id={id}
          type={type}
          value={value}
          onChange={(e) => onChange(e.target.value)}
          placeholder={placeholder}
          className={cn(
            "w-full rounded-lg border border-[--color-border] py-2.5 text-sm placeholder-gray-400 focus:border-[--color-brand-500] focus:outline-none focus:ring-2 focus:ring-[--color-brand-500]/20",
            prefix ? "pl-10 pr-3.5" : "px-3.5",
            suffix ? "pr-16" : "",
          )}
        />
        {suffix && (
          <span className="pointer-events-none absolute right-3 text-sm text-gray-400">{suffix}</span>
        )}
      </div>
      {hint && <p className="mt-1 text-xs text-gray-400">{hint}</p>}
    </div>
  )
}

// ─── Main page ────────────────────────────────────────────────────────────────

const STEPS = [
  { title: "Your goal", icon: User },
  { title: "Finances", icon: Wallet },
  { title: "Household", icon: Home },
  { title: "Preferences", icon: MapPin },
]

export default function OnboardingPage() {
  const router = useRouter()
  const setUser = useAuthStore((s) => s.setUser)
  const user = useAuthStore((s) => s.user)

  const [step, setStep] = useState(0)
  const [loading, setLoading] = useState(false)
  const [error, setError] = useState<string | null>(null)

  const [identity, setIdentity] = useState<IdentityStep>({ purpose: "", urgency: "" })
  const [finance, setFinance] = useState<FinanceStep>({
    monthly_gross: "", monthly_takehome: "", monthly_expenses: "",
    existing_debts: "", savings: "", is_ofw: false,
    target_amort_min: "", target_amort_max: "",
  })
  const [household, setHousehold] = useState<HouseholdStep>({
    family_size: "2", commute_mode: "", max_commute_minutes: "60",
    needs_school: false, needs_hospital: false, work_city: "",
  })
  const [prefs, setPrefs] = useState<PreferenceStep>({
    property_types: [], preferred_lgus: [], risk_tolerance: "",
    must_haves: [], deal_breakers: [],
  })

  function canProceed(): boolean {
    if (step === 0) return !!identity.purpose && !!identity.urgency
    if (step === 1) return !!finance.monthly_takehome
    if (step === 2) return !!household.commute_mode
    return prefs.property_types.length > 0 && !!prefs.risk_tolerance
  }

  async function handleSubmit() {
    setLoading(true)
    setError(null)
    try {
      const payload = {
        purpose: identity.purpose,
        urgency: identity.urgency,
        monthly_gross: finance.monthly_gross ? Number(finance.monthly_gross) : null,
        monthly_takehome: finance.monthly_takehome ? Number(finance.monthly_takehome) : null,
        monthly_expenses: finance.monthly_expenses ? Number(finance.monthly_expenses) : null,
        existing_debts: finance.existing_debts ? Number(finance.existing_debts) : null,
        savings: finance.savings ? Number(finance.savings) : null,
        is_ofw: finance.is_ofw,
        target_amort_min: finance.target_amort_min ? Number(finance.target_amort_min) : null,
        target_amort_max: finance.target_amort_max ? Number(finance.target_amort_max) : null,
        family_size: Number(household.family_size),
        commute_mode: household.commute_mode,
        max_commute_minutes: Number(household.max_commute_minutes),
        needs_school: household.needs_school,
        needs_hospital: household.needs_hospital,
        work_city: household.work_city || null,
        property_types: prefs.property_types,
        preferred_lgus: prefs.preferred_lgus,
        risk_tolerance: prefs.risk_tolerance,
        must_haves: prefs.must_haves,
        deal_breakers: prefs.deal_breakers,
      }
      await apiFetch("/v1/profile", { method: "POST", body: JSON.stringify(payload) })
      if (user) setUser({ ...user, has_profile: true })
      router.push("/dashboard")
    } catch (err) {
      setError(err instanceof Error ? err.message : "Could not save profile. Please try again.")
    } finally {
      setLoading(false)
    }
  }

  return (
    <div className="mx-auto max-w-2xl">
      {/* Header */}
      <div className="mb-8 text-center">
        <h1 className="text-2xl font-semibold text-gray-900">Set up your profile</h1>
        <p className="mt-1.5 text-sm text-gray-500">
          This helps us give you accurate, personalized recommendations.
        </p>
      </div>

      {/* Step indicator */}
      <div className="mb-8 flex items-center justify-between">
        {STEPS.map((s, i) => {
          const Icon = s.icon
          const done = i < step
          const active = i === step
          return (
            <div key={s.title} className="flex flex-1 items-center">
              <div className="flex flex-col items-center">
                <div
                  className={cn(
                    "flex h-9 w-9 items-center justify-center rounded-full text-sm font-semibold transition",
                    done
                      ? "bg-[--color-brand-600] text-white"
                      : active
                        ? "border-2 border-[--color-brand-600] text-[--color-brand-600]"
                        : "border-2 border-gray-200 text-gray-300",
                  )}
                >
                  {done ? <Check className="h-4 w-4" /> : <Icon className="h-4 w-4" />}
                </div>
                <span
                  className={cn(
                    "mt-1.5 text-xs",
                    active ? "font-semibold text-[--color-brand-700]" : "text-gray-400",
                  )}
                >
                  {s.title}
                </span>
              </div>
              {i < STEPS.length - 1 && (
                <div
                  className={cn(
                    "mx-2 h-px flex-1 -translate-y-3",
                    i < step ? "bg-[--color-brand-400]" : "bg-gray-200",
                  )}
                />
              )}
            </div>
          )
        })}
      </div>

      {/* Step content */}
      <div className="rounded-2xl border border-[--color-border] bg-white px-8 py-8">
        {step === 0 && (
          <div className="space-y-6">
            <div>
              <p className="mb-3 text-sm font-medium text-gray-700">What are you looking for?</p>
              <ChipGroup
                options={[
                  { value: "residence", label: "A home to live in" },
                  { value: "investment", label: "An investment property" },
                  { value: "both", label: "Both" },
                ]}
                selected={identity.purpose ? [identity.purpose] : []}
                onChange={([v]) => setIdentity((p) => ({ ...p, purpose: (v ?? "") as IdentityStep["purpose"] }))}
                multi={false}
              />
            </div>
            <div>
              <p className="mb-3 text-sm font-medium text-gray-700">How soon are you looking to buy?</p>
              <ChipGroup
                options={[
                  { value: "asap", label: "As soon as possible" },
                  { value: "6months", label: "Within 6 months" },
                  { value: "1year", label: "Within a year" },
                  { value: "exploring", label: "Just exploring" },
                ]}
                selected={identity.urgency ? [identity.urgency] : []}
                onChange={([v]) => setIdentity((p) => ({ ...p, urgency: (v ?? "") as IdentityStep["urgency"] }))}
                multi={false}
              />
            </div>
          </div>
        )}

        {step === 1 && (
          <div className="space-y-5">
            <FieldInput
              label="Monthly take-home pay (required)"
              id="takehome"
              type="number"
              prefix="₱"
              value={finance.monthly_takehome}
              onChange={(v) => setFinance((p) => ({ ...p, monthly_takehome: v }))}
              placeholder="60,000"
              hint="Your net pay after tax and mandatory deductions."
            />
            <FieldInput
              label="Monthly gross income (optional)"
              id="gross"
              type="number"
              prefix="₱"
              value={finance.monthly_gross}
              onChange={(v) => setFinance((p) => ({ ...p, monthly_gross: v }))}
              placeholder="75,000"
            />
            <div className="grid gap-5 sm:grid-cols-2">
              <FieldInput
                label="Monthly expenses"
                id="expenses"
                type="number"
                prefix="₱"
                value={finance.monthly_expenses}
                onChange={(v) => setFinance((p) => ({ ...p, monthly_expenses: v }))}
                placeholder="25,000"
              />
              <FieldInput
                label="Existing monthly debt payments"
                id="debts"
                type="number"
                prefix="₱"
                value={finance.existing_debts}
                onChange={(v) => setFinance((p) => ({ ...p, existing_debts: v }))}
                placeholder="5,000"
                hint="Car loans, credit card min, etc."
              />
            </div>
            <FieldInput
              label="Total savings / down payment funds"
              id="savings"
              type="number"
              prefix="₱"
              value={finance.savings}
              onChange={(v) => setFinance((p) => ({ ...p, savings: v }))}
              placeholder="500,000"
            />
            <div className="grid gap-5 sm:grid-cols-2">
              <FieldInput
                label="Target monthly amortization (min)"
                id="amort_min"
                type="number"
                prefix="₱"
                value={finance.target_amort_min}
                onChange={(v) => setFinance((p) => ({ ...p, target_amort_min: v }))}
                placeholder="15,000"
              />
              <FieldInput
                label="Target monthly amortization (max)"
                id="amort_max"
                type="number"
                prefix="₱"
                value={finance.target_amort_max}
                onChange={(v) => setFinance((p) => ({ ...p, target_amort_max: v }))}
                placeholder="25,000"
              />
            </div>
            <label className="flex cursor-pointer items-center gap-3">
              <input
                type="checkbox"
                checked={finance.is_ofw}
                onChange={(e) => setFinance((p) => ({ ...p, is_ofw: e.target.checked }))}
                className="h-4 w-4 rounded border-gray-300 accent-[--color-brand-600]"
              />
              <span className="text-sm text-gray-700">
                I am an OFW / my income is in foreign currency
              </span>
            </label>
          </div>
        )}

        {step === 2 && (
          <div className="space-y-6">
            <div className="grid gap-5 sm:grid-cols-2">
              <div>
                <label className="mb-1.5 block text-sm font-medium text-gray-700">
                  Household size
                </label>
                <select
                  value={household.family_size}
                  onChange={(e) => setHousehold((p) => ({ ...p, family_size: e.target.value }))}
                  className="w-full rounded-lg border border-[--color-border] px-3.5 py-2.5 text-sm focus:border-[--color-brand-500] focus:outline-none focus:ring-2 focus:ring-[--color-brand-500]/20"
                >
                  {[1, 2, 3, 4, 5, 6, 7, 8].map((n) => (
                    <option key={n} value={n}>
                      {n === 1 ? "Solo" : `${n} people`}
                    </option>
                  ))}
                  <option value="9">9+ people</option>
                </select>
              </div>
              <FieldInput
                label="Max commute time (minutes)"
                id="commute"
                type="number"
                value={household.max_commute_minutes}
                onChange={(v) => setHousehold((p) => ({ ...p, max_commute_minutes: v }))}
                suffix="min"
                placeholder="60"
              />
            </div>

            <div>
              <p className="mb-3 text-sm font-medium text-gray-700">Typical commute mode</p>
              <ChipGroup
                options={[
                  { value: "car", label: "Private car" },
                  { value: "transit", label: "Public transit" },
                  { value: "mixed", label: "Both" },
                ]}
                selected={household.commute_mode ? [household.commute_mode] : []}
                onChange={([v]) =>
                  setHousehold((p) => ({ ...p, commute_mode: (v ?? "") as HouseholdStep["commute_mode"] }))
                }
                multi={false}
              />
            </div>

            <FieldInput
              label="Primary work / school city"
              id="work_city"
              value={household.work_city}
              onChange={(v) => setHousehold((p) => ({ ...p, work_city: v }))}
              placeholder="e.g. Makati, BGC, Alabang"
              hint="Used to calculate commute scores."
            />

            <div className="flex gap-6">
              <label className="flex cursor-pointer items-center gap-2.5">
                <input
                  type="checkbox"
                  checked={household.needs_school}
                  onChange={(e) => setHousehold((p) => ({ ...p, needs_school: e.target.checked }))}
                  className="h-4 w-4 rounded border-gray-300 accent-[--color-brand-600]"
                />
                <span className="text-sm text-gray-700">School proximity matters</span>
              </label>
              <label className="flex cursor-pointer items-center gap-2.5">
                <input
                  type="checkbox"
                  checked={household.needs_hospital}
                  onChange={(e) => setHousehold((p) => ({ ...p, needs_hospital: e.target.checked }))}
                  className="h-4 w-4 rounded border-gray-300 accent-[--color-brand-600]"
                />
                <span className="text-sm text-gray-700">Hospital proximity matters</span>
              </label>
            </div>
          </div>
        )}

        {step === 3 && (
          <div className="space-y-6">
            <div>
              <p className="mb-3 text-sm font-medium text-gray-700">
                Property types you're open to
              </p>
              <ChipGroup
                options={PROPERTY_TYPE_OPTIONS}
                selected={prefs.property_types}
                onChange={(v) => setPrefs((p) => ({ ...p, property_types: v }))}
              />
            </div>

            <div>
              <p className="mb-3 text-sm font-medium text-gray-700">Risk tolerance</p>
              <ChipGroup
                options={[
                  { value: "conservative", label: "Conservative — safety first" },
                  { value: "balanced", label: "Balanced — growth with caution" },
                  { value: "aggressive", label: "Aggressive — maximize upside" },
                ]}
                selected={prefs.risk_tolerance ? [prefs.risk_tolerance] : []}
                onChange={([v]) =>
                  setPrefs((p) => ({ ...p, risk_tolerance: (v ?? "") as PreferenceStep["risk_tolerance"] }))
                }
                multi={false}
              />
            </div>

            <div>
              <p className="mb-3 text-sm font-medium text-gray-700">Must-haves (optional)</p>
              <ChipGroup
                options={MUST_HAVE_OPTIONS.map((x) => ({ value: x, label: x }))}
                selected={prefs.must_haves}
                onChange={(v) => setPrefs((p) => ({ ...p, must_haves: v }))}
              />
            </div>

            <div>
              <p className="mb-3 text-sm font-medium text-gray-700">Deal-breakers (optional)</p>
              <ChipGroup
                options={DEAL_BREAKER_OPTIONS.map((x) => ({ value: x, label: x }))}
                selected={prefs.deal_breakers}
                onChange={(v) => setPrefs((p) => ({ ...p, deal_breakers: v }))}
              />
            </div>
          </div>
        )}

        {error && (
          <div className="mt-5 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-700">{error}</div>
        )}

        {/* Nav buttons */}
        <div className="mt-8 flex items-center justify-between">
          {step > 0 ? (
            <button
              type="button"
              onClick={() => setStep((s) => s - 1)}
              className="flex items-center gap-1.5 text-sm text-gray-500 transition hover:text-gray-700"
            >
              <ChevronLeft className="h-4 w-4" />
              Back
            </button>
          ) : (
            <div />
          )}

          {step < STEPS.length - 1 ? (
            <button
              type="button"
              onClick={() => setStep((s) => s + 1)}
              disabled={!canProceed()}
              className="flex items-center gap-1.5 rounded-lg bg-[--color-brand-600] px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-[--color-brand-700] disabled:opacity-50"
            >
              Continue
              <ChevronRight className="h-4 w-4" />
            </button>
          ) : (
            <button
              type="button"
              onClick={() => void handleSubmit()}
              disabled={!canProceed() || loading}
              className="flex items-center gap-1.5 rounded-lg bg-[--color-brand-600] px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-[--color-brand-700] disabled:opacity-50"
            >
              {loading ? "Saving…" : "Finish setup"}
              {!loading && <Check className="h-4 w-4" />}
            </button>
          )}
        </div>
      </div>

      <p className="mt-4 text-center text-xs text-gray-400">
        You can update these details anytime from your profile settings.
      </p>
    </div>
  )
}
