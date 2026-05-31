import Link from "next/link"
import { MapPin, Sparkles, ShieldCheck, TrendingUp } from "lucide-react"

export default function LandingPage() {
  return (
    <div className="min-h-screen bg-[--color-surface]">
      {/* Nav */}
      <header className="sticky top-0 z-50 border-b border-[--color-border] bg-white/80 backdrop-blur">
        <div className="mx-auto flex h-16 max-w-7xl items-center justify-between px-4 sm:px-6">
          <Link href="/" className="flex items-center gap-2">
            <span className="text-2xl font-bold text-[--color-brand-600]">HanapBahay</span>
            <span className="rounded-full bg-[--color-brand-100] px-2 py-0.5 text-xs font-semibold text-[--color-brand-700]">
              AI
            </span>
          </Link>
          <nav className="hidden items-center gap-6 text-sm font-medium text-gray-600 sm:flex">
            <Link href="/listings" className="hover:text-gray-900">
              Browse Properties
            </Link>
            <Link href="/financial" className="hover:text-gray-900">
              Loan Calculator
            </Link>
            <Link href="/about" className="hover:text-gray-900">
              About
            </Link>
          </nav>
          <div className="flex items-center gap-3">
            <Link
              href="/login"
              className="rounded-lg px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100"
            >
              Sign in
            </Link>
            <Link
              href="/register"
              className="rounded-lg bg-[--color-brand-600] px-4 py-2 text-sm font-medium text-white hover:bg-[--color-brand-700]"
            >
              Get started
            </Link>
          </div>
        </div>
      </header>

      {/* Hero */}
      <section className="mx-auto max-w-5xl px-4 pb-24 pt-20 sm:px-6 sm:pt-32">
        <div className="text-center">
          <p className="mb-4 inline-flex items-center gap-2 rounded-full border border-[--color-brand-200] bg-[--color-brand-50] px-4 py-1.5 text-sm font-medium text-[--color-brand-700]">
            <Sparkles className="h-3.5 w-3.5" />
            Powered by Claude — your fiduciary real estate advisor
          </p>
          <h1 className="font-serif text-4xl font-bold leading-tight text-gray-900 sm:text-6xl">
            Find the right home,
            <br />
            <span className="text-[--color-brand-600]">not just any home.</span>
          </h1>
          <p className="mx-auto mt-6 max-w-2xl text-lg text-gray-600">
            HanapBahay AI matches you to properties you can actually afford and sustain — with
            transparent scores, Pag-IBIG + bank loan simulations, flood risk warnings, and an AI
            advisor that prioritizes your financial stability over commissions.
          </p>
          <div className="mt-10 flex flex-col items-center gap-3 sm:flex-row sm:justify-center">
            <Link
              href="/register"
              className="w-full rounded-xl bg-[--color-brand-600] px-8 py-3.5 text-base font-semibold text-white shadow-sm hover:bg-[--color-brand-700] sm:w-auto"
            >
              Start your free consultation
            </Link>
            <Link
              href="/listings"
              className="flex w-full items-center justify-center gap-2 rounded-xl border border-[--color-border] bg-white px-8 py-3.5 text-base font-semibold text-gray-700 hover:bg-gray-50 sm:w-auto"
            >
              <MapPin className="h-4 w-4" />
              Browse properties
            </Link>
          </div>
          <p className="mt-4 text-sm text-gray-500">
            Free to use · No broker commissions charged to buyers
          </p>
        </div>
      </section>

      {/* Value props */}
      <section className="border-t border-[--color-border] bg-white">
        <div className="mx-auto max-w-7xl px-4 py-20 sm:px-6">
          <div className="grid gap-10 sm:grid-cols-3">
            <div>
              <div className="mb-4 inline-flex h-12 w-12 items-center justify-center rounded-xl bg-[--color-brand-100]">
                <ShieldCheck className="h-6 w-6 text-[--color-brand-600]" />
              </div>
              <h3 className="mb-2 text-lg font-semibold text-gray-900">Affordability first</h3>
              <p className="text-gray-600">
                Every listing shows your estimated monthly amortization and debt-to-income ratio
                upfront. Properties outside your budget are flagged — never hidden.
              </p>
            </div>
            <div>
              <div className="mb-4 inline-flex h-12 w-12 items-center justify-center rounded-xl bg-[--color-brand-100]">
                <Sparkles className="h-6 w-6 text-[--color-brand-600]" />
              </div>
              <h3 className="mb-2 text-lg font-semibold text-gray-900">AI that explains itself</h3>
              <p className="text-gray-600">
                Claude reviews your profile and shortlist, produces a scored rationale for each
                recommendation, and names exactly what you're giving up with every trade-off.
              </p>
            </div>
            <div>
              <div className="mb-4 inline-flex h-12 w-12 items-center justify-center rounded-xl bg-[--color-brand-100]">
                <TrendingUp className="h-6 w-6 text-[--color-brand-600]" />
              </div>
              <h3 className="mb-2 text-lg font-semibold text-gray-900">Philippine-grade data</h3>
              <p className="text-gray-600">
                Flood hazard maps, fault-line distances, Pag-IBIG rules, BIR zonal values, commute
                matrices to top employment hubs — baked in, not a paid add-on.
              </p>
            </div>
          </div>
        </div>
      </section>

      {/* Footer */}
      <footer className="border-t border-[--color-border] bg-[--color-surface]">
        <div className="mx-auto flex max-w-7xl flex-col items-center justify-between gap-4 px-4 py-10 text-sm text-gray-500 sm:flex-row sm:px-6">
          <p>© {new Date().getFullYear()} HanapBahay AI. Independent of all developers and brokers.</p>
          <div className="flex gap-5">
            <Link href="/privacy" className="hover:text-gray-700">
              Privacy Policy
            </Link>
            <Link href="/terms" className="hover:text-gray-700">
              Terms
            </Link>
            <Link href="/contact" className="hover:text-gray-700">
              Contact
            </Link>
          </div>
        </div>
      </footer>
    </div>
  )
}
