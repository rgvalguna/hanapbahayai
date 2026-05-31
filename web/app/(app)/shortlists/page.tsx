import type { Metadata } from "next"

export const metadata: Metadata = { title: "Shortlists" }

export default function ShortlistsPage() {
  return (
    <div>
      <div className="mb-6 flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-semibold text-gray-900">Your shortlists</h1>
          <p className="mt-1 text-sm text-gray-500">
            Saved properties grouped by criteria or decision stage.
          </p>
        </div>
        <button className="rounded-lg bg-[--color-brand-600] px-4 py-2 text-sm font-semibold text-white transition hover:bg-[--color-brand-700]">
          + New shortlist
        </button>
      </div>

      <div className="flex flex-col items-center justify-center rounded-2xl border border-dashed border-[--color-border] bg-white py-24 text-center">
        <div className="mb-3 flex h-12 w-12 items-center justify-center rounded-full bg-[--color-brand-50] text-[--color-brand-400]">
          <svg
            className="h-6 w-6"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
            aria-hidden="true"
          >
            <path
              strokeLinecap="round"
              strokeLinejoin="round"
              strokeWidth={1.5}
              d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"
            />
          </svg>
        </div>
        <p className="mb-1 text-sm font-medium text-gray-700">No saved properties yet</p>
        <p className="mb-4 max-w-xs text-xs text-gray-400">
          Browse properties and save the ones you want to compare later. You can create multiple
          shortlists for different goals.
        </p>
        <a
          href="/listings"
          className="rounded-lg border border-[--color-brand-300] px-4 py-2 text-sm font-medium text-[--color-brand-700] transition hover:bg-[--color-brand-50]"
        >
          Browse properties
        </a>
      </div>
    </div>
  )
}
