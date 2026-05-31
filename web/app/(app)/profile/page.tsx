"use client"

import { useState } from "react"
import { useRouter } from "next/navigation"
import { User, Wallet, Shield, Bell, Trash2, ChevronRight } from "lucide-react"
import { cn } from "@/lib/utils"
import { apiFetch } from "@/lib/api/client"
import { useAuthStore } from "@/lib/store/auth"

type Tab = "account" | "finances" | "privacy" | "notifications"

const TABS: { key: Tab; label: string; icon: React.ElementType }[] = [
  { key: "account", label: "Account", icon: User },
  { key: "finances", label: "Finances", icon: Wallet },
  { key: "privacy", label: "Privacy", icon: Shield },
  { key: "notifications", label: "Notifications", icon: Bell },
]

// ─── Account tab ──────────────────────────────────────────────────────────────

function AccountTab() {
  const user = useAuthStore((s) => s.user)
  const setUser = useAuthStore((s) => s.setUser)
  const [name, setName] = useState(user?.name ?? "")
  const [loading, setLoading] = useState(false)
  const [success, setSuccess] = useState(false)
  const [error, setError] = useState<string | null>(null)

  async function save() {
    if (!name.trim()) return
    setLoading(true)
    setSuccess(false)
    setError(null)
    try {
      await apiFetch("/v1/auth/user", { method: "PATCH", body: JSON.stringify({ name }) })
      if (user) setUser({ ...user, name })
      setSuccess(true)
    } catch (err) {
      setError(err instanceof Error ? err.message : "Update failed.")
    } finally {
      setLoading(false)
    }
  }

  return (
    <div className="space-y-6">
      <div>
        <h2 className="mb-1 text-base font-semibold text-gray-900">Account details</h2>
        <p className="text-sm text-gray-500">Update your name and contact information.</p>
      </div>

      <div className="space-y-4">
        <div>
          <label htmlFor="profile-name" className="mb-1.5 block text-sm font-medium text-gray-700">
            Full name
          </label>
          <input
            id="profile-name"
            type="text"
            value={name}
            onChange={(e) => setName(e.target.value)}
            className="w-full max-w-sm rounded-lg border border-[--color-border] px-3.5 py-2.5 text-sm focus:border-[--color-brand-500] focus:outline-none focus:ring-2 focus:ring-[--color-brand-500]/20"
          />
        </div>
        <div>
          <label className="mb-1.5 block text-sm font-medium text-gray-700">Email address</label>
          <input
            type="email"
            value={user?.email ?? ""}
            disabled
            className="w-full max-w-sm rounded-lg border border-[--color-border] bg-gray-50 px-3.5 py-2.5 text-sm text-gray-400"
          />
          <p className="mt-1 text-xs text-gray-400">
            Email changes require identity verification.
          </p>
        </div>
      </div>

      {success && (
        <p className="rounded-lg bg-green-50 px-4 py-2.5 text-sm text-green-700">
          Changes saved successfully.
        </p>
      )}
      {error && (
        <p className="rounded-lg bg-red-50 px-4 py-2.5 text-sm text-red-700">{error}</p>
      )}

      <button
        onClick={() => void save()}
        disabled={loading || name === user?.name}
        className="rounded-lg bg-[--color-brand-600] px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-[--color-brand-700] disabled:opacity-50"
      >
        {loading ? "Saving…" : "Save changes"}
      </button>

      <div className="border-t border-[--color-border] pt-6">
        <h3 className="mb-1 text-sm font-semibold text-gray-900">Change password</h3>
        <p className="mb-3 text-sm text-gray-500">
          We'll send a secure link to your email address.
        </p>
        <button className="text-sm font-medium text-[--color-brand-600] hover:underline">
          Send reset link
        </button>
      </div>
    </div>
  )
}

// ─── Finances tab ─────────────────────────────────────────────────────────────

function FinancesTab() {
  const router = useRouter()
  return (
    <div className="space-y-6">
      <div>
        <h2 className="mb-1 text-base font-semibold text-gray-900">Financial profile</h2>
        <p className="text-sm text-gray-500">
          Your financial details power the loan simulator and affordability scoring. They are
          encrypted at rest and never shared with third parties.
        </p>
      </div>

      <div className="rounded-xl border border-[--color-border] bg-[--color-surface] p-5">
        <div className="mb-4 flex items-center justify-between">
          <span className="text-sm font-medium text-gray-700">Profile last updated</span>
          <span className="text-xs text-gray-400">—</span>
        </div>
        <button
          onClick={() => router.push("/profile/onboarding")}
          className="flex w-full items-center justify-between rounded-lg border border-[--color-border] bg-white px-4 py-3 text-sm transition hover:bg-gray-50"
        >
          <span className="font-medium text-gray-700">Re-run profile wizard</span>
          <ChevronRight className="h-4 w-4 text-gray-400" />
        </button>
      </div>

      <div className="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
        <strong>Keep this current.</strong> Outdated income figures lead to inaccurate
        affordability scores and DTI calculations. We recommend updating whenever your income or
        debt situation changes.
      </div>
    </div>
  )
}

// ─── Privacy tab ──────────────────────────────────────────────────────────────

function PrivacyTab() {
  const router = useRouter()
  const clearUser = useAuthStore((s) => s.clearUser)
  const [deleteConfirm, setDeleteConfirm] = useState(false)
  const [deleting, setDeleting] = useState(false)

  async function handleDelete() {
    setDeleting(true)
    try {
      await apiFetch("/v1/auth/account", { method: "DELETE" })
      clearUser()
      router.push("/login")
    } catch {
      setDeleting(false)
    }
  }

  return (
    <div className="space-y-6">
      <div>
        <h2 className="mb-1 text-base font-semibold text-gray-900">Privacy & data</h2>
        <p className="text-sm text-gray-500">
          Your rights under the Philippine Data Privacy Act (RA 10173).
        </p>
      </div>

      <div className="divide-y divide-[--color-border] rounded-xl border border-[--color-border]">
        {[
          {
            title: "Download my data",
            desc: "Export all your profile, financial, and conversation data as JSON.",
            action: "Request export",
          },
          {
            title: "Delete conversation history",
            desc: "Permanently remove all AI consultation transcripts. Shortlists are kept.",
            action: "Delete history",
          },
        ].map((item) => (
          <div key={item.title} className="flex items-center justify-between px-5 py-4">
            <div>
              <p className="text-sm font-medium text-gray-800">{item.title}</p>
              <p className="text-xs text-gray-500">{item.desc}</p>
            </div>
            <button className="ml-4 shrink-0 text-xs font-medium text-[--color-brand-600] hover:underline">
              {item.action}
            </button>
          </div>
        ))}
      </div>

      <div className="rounded-xl border border-red-200 bg-red-50 p-5">
        <h3 className="mb-1 flex items-center gap-2 text-sm font-semibold text-red-700">
          <Trash2 className="h-4 w-4" />
          Delete account
        </h3>
        <p className="mb-4 text-xs text-red-600">
          Permanently deletes all data associated with your account. This cannot be undone.
        </p>
        {!deleteConfirm ? (
          <button
            onClick={() => setDeleteConfirm(true)}
            className="rounded-lg border border-red-300 bg-white px-4 py-2 text-xs font-semibold text-red-600 transition hover:bg-red-50"
          >
            Delete my account
          </button>
        ) : (
          <div className="flex gap-3">
            <button
              onClick={() => void handleDelete()}
              disabled={deleting}
              className="rounded-lg bg-red-600 px-4 py-2 text-xs font-semibold text-white transition hover:bg-red-700 disabled:opacity-60"
            >
              {deleting ? "Deleting…" : "Yes, delete permanently"}
            </button>
            <button
              onClick={() => setDeleteConfirm(false)}
              className="rounded-lg border border-[--color-border] px-4 py-2 text-xs font-medium text-gray-600 transition hover:bg-gray-50"
            >
              Cancel
            </button>
          </div>
        )}
      </div>
    </div>
  )
}

// ─── Notifications tab ────────────────────────────────────────────────────────

const NOTIF_OPTIONS = [
  { key: "price_drop", label: "Price drop alerts", desc: "When a shortlisted property drops in price." },
  { key: "new_match", label: "New property matches", desc: "When a new listing matches your profile." },
  { key: "ai_insights", label: "AI market insights", desc: "Weekly digest of market trends in your target areas." },
  { key: "account", label: "Account & security", desc: "Login activity, profile changes, and important notices.", locked: true },
]

function NotificationsTab() {
  const [prefs, setPrefs] = useState<Record<string, boolean>>({
    price_drop: true, new_match: true, ai_insights: false, account: true,
  })

  function toggle(key: string) {
    setPrefs((p) => ({ ...p, [key]: !p[key] }))
  }

  return (
    <div className="space-y-6">
      <div>
        <h2 className="mb-1 text-base font-semibold text-gray-900">Notification preferences</h2>
        <p className="text-sm text-gray-500">
          Control which email notifications you receive.
        </p>
      </div>

      <div className="divide-y divide-[--color-border] rounded-xl border border-[--color-border]">
        {NOTIF_OPTIONS.map((opt) => (
          <div key={opt.key} className="flex items-center justify-between px-5 py-4">
            <div className="mr-4">
              <p className="text-sm font-medium text-gray-800">{opt.label}</p>
              <p className="text-xs text-gray-500">{opt.desc}</p>
            </div>
            <button
              type="button"
              role="switch"
              aria-checked={prefs[opt.key]}
              onClick={() => !opt.locked && toggle(opt.key)}
              className={cn(
                "relative inline-flex h-5 w-9 shrink-0 cursor-pointer items-center rounded-full transition",
                prefs[opt.key] ? "bg-[--color-brand-600]" : "bg-gray-200",
                opt.locked && "cursor-not-allowed opacity-60",
              )}
            >
              <span
                className={cn(
                  "inline-block h-3.5 w-3.5 rounded-full bg-white shadow transition-transform",
                  prefs[opt.key] ? "translate-x-4" : "translate-x-0.5",
                )}
              />
            </button>
          </div>
        ))}
      </div>
    </div>
  )
}

// ─── Page ─────────────────────────────────────────────────────────────────────

export default function ProfilePage() {
  const [activeTab, setActiveTab] = useState<Tab>("account")

  const tabContent: Record<Tab, React.ReactNode> = {
    account: <AccountTab />,
    finances: <FinancesTab />,
    privacy: <PrivacyTab />,
    notifications: <NotificationsTab />,
  }

  return (
    <div>
      <div className="mb-6">
        <h1 className="text-2xl font-semibold text-gray-900">Profile & settings</h1>
        <p className="mt-1 text-sm text-gray-500">
          Manage your account, financial profile, and preferences.
        </p>
      </div>

      <div className="flex gap-8">
        {/* Side nav */}
        <nav className="hidden w-44 shrink-0 flex-col gap-1 lg:flex">
          {TABS.map(({ key, label, icon: Icon }) => (
            <button
              key={key}
              onClick={() => setActiveTab(key)}
              className={cn(
                "flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm transition",
                activeTab === key
                  ? "bg-[--color-brand-50] font-semibold text-[--color-brand-700]"
                  : "text-gray-600 hover:bg-gray-50 hover:text-gray-900",
              )}
            >
              <Icon className="h-4 w-4" />
              {label}
            </button>
          ))}
        </nav>

        {/* Mobile tab bar */}
        <div className="mb-4 flex gap-1 overflow-x-auto lg:hidden">
          {TABS.map(({ key, label, icon: Icon }) => (
            <button
              key={key}
              onClick={() => setActiveTab(key)}
              className={cn(
                "flex shrink-0 items-center gap-1.5 rounded-lg px-3 py-2 text-xs transition",
                activeTab === key
                  ? "bg-[--color-brand-50] font-semibold text-[--color-brand-700]"
                  : "border border-[--color-border] text-gray-600",
              )}
            >
              <Icon className="h-3.5 w-3.5" />
              {label}
            </button>
          ))}
        </div>

        {/* Content */}
        <div className="min-w-0 flex-1 rounded-2xl border border-[--color-border] bg-white p-6 lg:p-8">
          {tabContent[activeTab]}
        </div>
      </div>
    </div>
  )
}
