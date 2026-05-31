import type { Metadata } from "next"

export const metadata: Metadata = { title: "Dashboard" }

export default function DashboardPage() {
  return (
    <div>
      <h1 className="mb-1 text-2xl font-semibold text-gray-900">Your dashboard</h1>
      <p className="text-sm text-gray-500">
        Welcome back. Pick up where you left off or start a new consultation.
      </p>

      <div className="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <DashboardCard
          href="/advisor"
          title="AI Consultation"
          description="Ask Claude about any property or let it build your shortlist from scratch."
          cta="Start chat"
          accent
        />
        <DashboardCard
          href="/listings"
          title="Browse Properties"
          description="Search with filters, view on map, and see affordability scores for each listing."
          cta="Search listings"
        />
        <DashboardCard
          href="/financial"
          title="Loan Simulator"
          description="Run Pag-IBIG and bank financing scenarios. Compare amortization timelines."
          cta="Open calculator"
        />
      </div>
    </div>
  )
}

function DashboardCard({
  href,
  title,
  description,
  cta,
  accent = false,
}: {
  href: string
  title: string
  description: string
  cta: string
  accent?: boolean
}) {
  return (
    <a
      href={href}
      className={[
        "group flex flex-col rounded-2xl border p-6 transition hover:shadow-[--shadow-elevated]",
        accent
          ? "border-[--color-brand-300] bg-[--color-brand-50] hover:border-[--color-brand-400]"
          : "border-[--color-border] bg-white",
      ].join(" ")}
    >
      <h2 className="mb-2 text-base font-semibold text-gray-900">{title}</h2>
      <p className="mb-4 flex-1 text-sm text-gray-600">{description}</p>
      <span
        className={[
          "text-sm font-medium",
          accent ? "text-[--color-brand-700]" : "text-[--color-brand-600]",
        ].join(" ")}
      >
        {cta} →
      </span>
    </a>
  )
}
