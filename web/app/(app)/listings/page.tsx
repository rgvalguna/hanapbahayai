import type { Metadata } from "next"
import { ListingGrid } from "@/components/listings/ListingGrid"
import { SearchFilters } from "@/components/listings/SearchFilters"

export const metadata: Metadata = { title: "Browse Properties" }

interface ListingsPageProps {
  searchParams: Promise<Record<string, string | string[] | undefined>>
}

export default async function ListingsPage({ searchParams }: ListingsPageProps) {
  const params = await searchParams
  return (
    <div>
      <div className="mb-6">
        <h1 className="text-2xl font-semibold text-gray-900">Browse properties</h1>
        <p className="mt-1 text-sm text-gray-500">
          Showing scores calculated for your profile. Amortization estimates use your target budget.
        </p>
      </div>

      {/* Filter bar */}
      <SearchFilters initialParams={params} />

      {/* Results */}
      <div className="mt-6">
        <ListingGrid searchParams={params} />
      </div>
    </div>
  )
}
