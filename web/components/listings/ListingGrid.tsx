import { ListingCard, type ListingCardData } from "./ListingCard"

const API_BASE = process.env.NEXT_PUBLIC_API_URL ?? "http://localhost:8000"

interface ListingGridProps {
  searchParams: Record<string, string | string[] | undefined>
}

async function fetchListings(
  searchParams: Record<string, string | string[] | undefined>,
): Promise<{ listings: ListingCardData[]; total: number }> {
  const qs = new URLSearchParams()
  for (const [key, val] of Object.entries(searchParams)) {
    if (val === undefined) continue
    if (Array.isArray(val)) {
      val.forEach((v) => qs.append(key, v))
    } else {
      qs.set(key, val)
    }
  }

  const res = await fetch(`${API_BASE}/api/v1/listings?${qs.toString()}`, {
    next: { revalidate: 60 },
  })

  if (!res.ok) return { listings: [], total: 0 }

  const json = (await res.json()) as {
    data: { listings: ListingCardData[]; meta: { total: number } }
  }
  return { listings: json.data.listings, total: json.data.meta.total }
}

export async function ListingGrid({ searchParams }: ListingGridProps) {
  const { listings, total } = await fetchListings(searchParams)

  if (!listings.length) {
    return (
      <div className="flex flex-col items-center justify-center rounded-2xl border border-dashed border-[--color-border] bg-white py-20 text-center">
        <p className="mb-1 text-sm font-medium text-gray-700">No properties found</p>
        <p className="text-xs text-gray-400">
          Try adjusting your filters or broadening your search area.
        </p>
      </div>
    )
  }

  return (
    <div>
      <p className="mb-4 text-xs text-gray-500">
        {total.toLocaleString("en-PH")} propert{total === 1 ? "y" : "ies"} found
      </p>
      <div className="grid gap-5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
        {listings.map((l) => (
          <ListingCard key={l.slug} listing={l} />
        ))}
      </div>
    </div>
  )
}
