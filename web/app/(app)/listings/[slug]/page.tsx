import type { Metadata } from "next"
import { notFound } from "next/navigation"
import { ScoreBadge } from "@/components/listings/ScoreBadge"
import { WarningChips } from "@/components/listings/WarningChips"
import { LoanSimulator } from "@/components/financial/LoanSimulator"

interface Props {
  params: Promise<{ slug: string }>
}

async function fetchListing(slug: string) {
  const apiUrl = process.env.NEXT_PUBLIC_API_URL ?? "http://localhost:8000"
  const res = await fetch(`${apiUrl}/api/v1/listings/${slug}`, {
    next: { revalidate: 60 }, // 60 s ISR — listings update frequently
  })

  if (res.status === 404) return null
  if (!res.ok) throw new Error(`API error ${res.status}`)

  const json = (await res.json()) as { data: { listing: Record<string, unknown> } }
  return json.data.listing
}

export async function generateMetadata({ params }: Props): Promise<Metadata> {
  const { slug } = await params
  const listing = await fetchListing(slug)
  if (!listing) return { title: "Listing not found" }

  return {
    title: `${listing["title"] as string} | HanapBahay AI`,
    description: `${listing["property_type"] as string} for sale in ${(listing["address"] as Record<string, string>)["city_muni_name"]}. PHP ${new Intl.NumberFormat("en-PH").format(listing["price_php"] as number)}.`,
  }
}

export default async function ListingDetailPage({ params }: Props) {
  const { slug } = await params
  const listing = await fetchListing(slug)
  if (!listing) notFound()

  const price = new Intl.NumberFormat("en-PH", {
    style: "currency",
    currency: "PHP",
    maximumFractionDigits: 0,
  }).format(listing["price_php"] as number)

  const address = listing["address"] as Record<string, string>

  return (
    <div className="grid gap-8 lg:grid-cols-[1fr,400px]">
      {/* Left: main content */}
      <div>
        {/* Photos */}
        <div className="mb-6 overflow-hidden rounded-2xl bg-gray-100">
          {/* eslint-disable-next-line @next/next/no-img-element */}
          <img
            src={(listing["photos"] as string[])[0] ?? "/placeholder-property.jpg"}
            alt={listing["title"] as string}
            className="h-80 w-full object-cover"
          />
        </div>

        {/* Header */}
        <div className="mb-4 flex flex-wrap items-start justify-between gap-4">
          <div>
            <h1 className="text-2xl font-semibold text-gray-900">{listing["title"] as string}</h1>
            <p className="mt-1 text-sm text-gray-500">
              {address["barangay_name"] ? `${address["barangay_name"]}, ` : ""}
              {address["city_muni_name"]}, {address["region_name"]}
            </p>
          </div>
          <div className="text-right">
            <div className="text-2xl font-bold text-gray-900">{price}</div>
            {listing["price_per_sqm_php"] && (
              <div className="text-sm text-gray-500">
                {new Intl.NumberFormat("en-PH", {
                  style: "currency",
                  currency: "PHP",
                  maximumFractionDigits: 0,
                }).format(listing["price_per_sqm_php"] as number)}{" "}
                / sqm
              </div>
            )}
          </div>
        </div>

        {/* Score + Warnings */}
        <div className="mb-6 flex flex-wrap items-center gap-3">
          {listing["score_cache"] && (
            <ScoreBadge
              total={
                Object.values(listing["score_cache"] as Record<string, number>).reduce(
                  (a, b) => a + b,
                  0,
                ) / Object.keys(listing["score_cache"] as object).length
              }
              dimensions={listing["score_cache"] as Record<string, number>}
            />
          )}
          <WarningChips fraudFlags={listing["fraud_flags"] as string[]} />
        </div>

        {/* Key specs */}
        <div className="mb-6 grid grid-cols-3 gap-4 rounded-xl border border-[--color-border] bg-white p-4">
          {listing["bedrooms"] != null && (
            <Spec label="Bedrooms" value={String(listing["bedrooms"])} />
          )}
          {listing["bathrooms"] != null && (
            <Spec label="Bathrooms" value={String(listing["bathrooms"])} />
          )}
          {listing["floor_area_sqm"] != null && (
            <Spec
              label="Floor area"
              value={`${listing["floor_area_sqm"] as number} sqm`}
            />
          )}
          {listing["lot_area_sqm"] != null && (
            <Spec label="Lot area" value={`${listing["lot_area_sqm"] as number} sqm`} />
          )}
          {listing["parking_slots"] != null && (
            <Spec label="Parking" value={String(listing["parking_slots"])} />
          )}
          {listing["year_built"] != null && (
            <Spec label="Year built" value={String(listing["year_built"])} />
          )}
        </div>

        {/* Description */}
        {listing["description"] && (
          <div className="prose prose-sm max-w-none text-gray-700">
            <h2 className="mb-2 text-base font-semibold text-gray-900">About this property</h2>
            <p className="whitespace-pre-line text-sm leading-relaxed">
              {listing["description"] as string}
            </p>
          </div>
        )}
      </div>

      {/* Right: financial sidebar */}
      <div className="space-y-4 lg:sticky lg:top-6 lg:self-start">
        <LoanSimulator
          listingPrice={listing["price_php"] as number}
          listingSlug={slug}
        />
      </div>
    </div>
  )
}

function Spec({ label, value }: { label: string; value: string }) {
  return (
    <div>
      <div className="text-xs text-gray-400">{label}</div>
      <div className="font-semibold text-gray-900">{value}</div>
    </div>
  )
}
