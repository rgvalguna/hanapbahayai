"use client"

import { useRouter, useSearchParams, usePathname } from "next/navigation"
import { useCallback } from "react"
import { Search, SlidersHorizontal, X } from "lucide-react"
import { cn } from "@/lib/utils"

interface SearchFiltersProps {
  initialParams: Record<string, string | string[] | undefined>
}

const PROPERTY_TYPES = [
  { value: "house_and_lot", label: "House & Lot" },
  { value: "condo", label: "Condo" },
  { value: "townhouse", label: "Townhouse" },
  { value: "lot", label: "Lot" },
  { value: "commercial", label: "Commercial" },
]

function asString(v: string | string[] | undefined): string {
  if (!v) return ""
  return Array.isArray(v) ? v[0] ?? "" : v
}

export function SearchFilters({ initialParams }: SearchFiltersProps) {
  const router = useRouter()
  const pathname = usePathname()
  const searchParams = useSearchParams()

  const push = useCallback(
    (key: string, value: string | null) => {
      const params = new URLSearchParams(searchParams.toString())
      if (value === null || value === "") {
        params.delete(key)
      } else {
        params.set(key, value)
      }
      params.delete("page")
      router.push(`${pathname}?${params.toString()}`)
    },
    [router, pathname, searchParams],
  )

  const q = asString(initialParams.q)
  const propertyType = asString(initialParams.property_type)
  const minPrice = asString(initialParams.min_price)
  const maxPrice = asString(initialParams.max_price)
  const minBeds = asString(initialParams.min_beds)

  const hasFilters = propertyType || minPrice || maxPrice || minBeds

  return (
    <div className="flex flex-wrap gap-3">
      {/* Keyword search */}
      <div className="relative min-w-[200px] flex-1">
        <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
        <input
          type="search"
          defaultValue={q}
          placeholder="City, barangay, or project name…"
          onChange={(e) => push("q", e.target.value || null)}
          className="w-full rounded-lg border border-[--color-border] py-2 pl-9 pr-3.5 text-sm placeholder-gray-400 focus:border-[--color-brand-500] focus:outline-none focus:ring-2 focus:ring-[--color-brand-500]/20"
        />
      </div>

      {/* Property type */}
      <select
        value={propertyType}
        onChange={(e) => push("property_type", e.target.value || null)}
        className="rounded-lg border border-[--color-border] bg-white px-3 py-2 text-sm text-gray-700 focus:border-[--color-brand-500] focus:outline-none focus:ring-2 focus:ring-[--color-brand-500]/20"
      >
        <option value="">All types</option>
        {PROPERTY_TYPES.map((pt) => (
          <option key={pt.value} value={pt.value}>
            {pt.label}
          </option>
        ))}
      </select>

      {/* Price range */}
      <div className="flex items-center gap-1.5">
        <input
          type="number"
          defaultValue={minPrice}
          placeholder="Min price"
          onBlur={(e) => push("min_price", e.target.value || null)}
          className="w-28 rounded-lg border border-[--color-border] px-3 py-2 text-sm placeholder-gray-400 focus:border-[--color-brand-500] focus:outline-none focus:ring-2 focus:ring-[--color-brand-500]/20"
        />
        <span className="text-xs text-gray-400">–</span>
        <input
          type="number"
          defaultValue={maxPrice}
          placeholder="Max price"
          onBlur={(e) => push("max_price", e.target.value || null)}
          className="w-28 rounded-lg border border-[--color-border] px-3 py-2 text-sm placeholder-gray-400 focus:border-[--color-brand-500] focus:outline-none focus:ring-2 focus:ring-[--color-brand-500]/20"
        />
      </div>

      {/* Min beds */}
      <select
        value={minBeds}
        onChange={(e) => push("min_beds", e.target.value || null)}
        className="rounded-lg border border-[--color-border] bg-white px-3 py-2 text-sm text-gray-700 focus:border-[--color-brand-500] focus:outline-none focus:ring-2 focus:ring-[--color-brand-500]/20"
      >
        <option value="">Any beds</option>
        {[1, 2, 3, 4, 5].map((n) => (
          <option key={n} value={String(n)}>
            {n}+ beds
          </option>
        ))}
      </select>

      {hasFilters && (
        <button
          onClick={() => router.push(pathname)}
          className={cn(
            "flex items-center gap-1.5 rounded-lg border border-gray-200 px-3 py-2 text-sm text-gray-500 transition hover:border-gray-300 hover:text-gray-700",
          )}
        >
          <X className="h-3.5 w-3.5" />
          Clear
        </button>
      )}

      <button
        className="ml-auto flex items-center gap-1.5 rounded-lg border border-[--color-border] bg-white px-3 py-2 text-sm text-gray-600 transition hover:bg-gray-50"
        aria-label="More filters"
      >
        <SlidersHorizontal className="h-3.5 w-3.5" />
        <span className="hidden sm:inline">More filters</span>
      </button>
    </div>
  )
}
