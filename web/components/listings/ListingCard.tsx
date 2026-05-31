import Link from "next/link"
import { BedDouble, Bath, Ruler, MapPin, TrendingUp } from "lucide-react"
import { cn, formatPHP, dtiClass } from "@/lib/utils"
import { ScoreBadge } from "./ScoreBadge"
import { WarningChips } from "./WarningChips"

export interface ListingCardData {
  slug: string
  title: string
  price_php: number
  monthly_amort_php?: number | null
  dti_ratio?: number | null
  property_type: string
  bedrooms?: number | null
  bathrooms?: number | null
  floor_area_sqm?: number | null
  photos?: string[]
  address: {
    city_muni_name?: string
    region_name?: string
  }
  score_cache?: Record<string, number>
  fraud_flags?: string[]
}

interface ListingCardProps {
  listing: ListingCardData
  className?: string
}

export function ListingCard({ listing, className }: ListingCardProps) {
  const scoreTotal =
    listing.score_cache && Object.keys(listing.score_cache).length > 0
      ? Object.values(listing.score_cache).reduce((a, b) => a + b, 0) /
        Object.keys(listing.score_cache).length
      : null

  const photo = listing.photos?.[0] ?? "/placeholder-property.jpg"

  return (
    <Link
      href={`/listings/${listing.slug}`}
      className={cn(
        "group flex flex-col overflow-hidden rounded-2xl border border-[--color-border] bg-white transition hover:border-[--color-brand-300] hover:shadow-[--shadow-elevated]",
        className,
      )}
    >
      {/* Photo */}
      <div className="relative aspect-[4/3] overflow-hidden bg-gray-100">
        {/* eslint-disable-next-line @next/next/no-img-element */}
        <img
          src={photo}
          alt={listing.title}
          className="h-full w-full object-cover transition-transform duration-300 group-hover:scale-105"
        />
        {scoreTotal !== null && (
          <div className="absolute right-2 top-2">
            <ScoreBadge total={scoreTotal} size="sm" />
          </div>
        )}
      </div>

      {/* Body */}
      <div className="flex flex-1 flex-col p-4">
        {/* Location */}
        <div className="mb-1 flex items-center gap-1 text-xs text-gray-400">
          <MapPin className="h-3 w-3" />
          <span className="truncate">
            {listing.address.city_muni_name}
            {listing.address.region_name ? `, ${listing.address.region_name}` : ""}
          </span>
        </div>

        {/* Title */}
        <h3 className="mb-2 line-clamp-2 text-sm font-semibold leading-snug text-gray-900 group-hover:text-[--color-brand-700]">
          {listing.title}
        </h3>

        {/* Specs */}
        <div className="mb-3 flex flex-wrap gap-3 text-xs text-gray-500">
          {listing.bedrooms != null && (
            <span className="flex items-center gap-1">
              <BedDouble className="h-3 w-3" /> {listing.bedrooms} bd
            </span>
          )}
          {listing.bathrooms != null && (
            <span className="flex items-center gap-1">
              <Bath className="h-3 w-3" /> {listing.bathrooms} ba
            </span>
          )}
          {listing.floor_area_sqm != null && (
            <span className="flex items-center gap-1">
              <Ruler className="h-3 w-3" /> {listing.floor_area_sqm} sqm
            </span>
          )}
        </div>

        {/* Warnings */}
        {listing.fraud_flags?.length ? (
          <div className="mb-3">
            <WarningChips fraudFlags={listing.fraud_flags} />
          </div>
        ) : null}

        {/* Footer: price + amort */}
        <div className="mt-auto flex items-end justify-between">
          <div>
            <div className="text-base font-bold text-gray-900">
              {formatPHP(listing.price_php)}
            </div>
            {listing.monthly_amort_php && (
              <div
                className={cn(
                  "text-xs font-medium",
                  listing.dti_ratio != null
                    ? dtiClass(listing.dti_ratio)
                    : "text-gray-500",
                )}
              >
                ~{formatPHP(listing.monthly_amort_php)}/mo
              </div>
            )}
          </div>
          {scoreTotal !== null && (
            <div className="flex items-center gap-1 text-xs text-gray-400">
              <TrendingUp className="h-3 w-3" />
              <span className="tabular-nums">{Math.round(scoreTotal)}</span>
            </div>
          )}
        </div>
      </div>
    </Link>
  )
}
