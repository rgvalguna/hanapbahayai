import { apiFetch } from "./client"

export type PropertyType = "condo" | "townhouse" | "single_detached" | "lot_only" | "commercial" | "warehouse"
export type ListingStatus = "active" | "under_review" | "sold" | "off_market" | "archived"

export interface Listing {
  id: string
  title: string
  description: string
  price_php: number
  price_per_sqm: number | null
  property_type: PropertyType
  status: ListingStatus
  bedrooms: number | null
  bathrooms: number | null
  floor_area_sqm: number | null
  lot_area_sqm: number | null
  hoa_php_monthly: number | null
  address: {
    region: string
    province: string
    city: string
    barangay: string
    street: string | null
    psgc_code: string | null
  }
  location: {
    lat: number
    lng: number
  }
  photos: string[]
  developer_id: string | null
  broker_id: string | null
  quality_score: number
  fraud_flags: string[]
  listing_date: string
  last_seen_at: string
}

export interface ListingScore {
  total: number
  affordability: number
  commute: number
  safety: number
  flood: number
  education: number
  healthcare: number
  internet: number
  investment: number
  developer: number
  livability: number
  rationale: string
  warnings: Array<{ severity: "low" | "medium" | "high"; type: string; message: string }>
}

export interface ScoredListing extends Listing {
  score: ListingScore
}

export interface SearchParams {
  q?: string
  city?: string
  property_type?: PropertyType
  min_price?: number
  max_price?: number
  min_bedrooms?: number
  max_bedrooms?: number
  lat?: number
  lng?: number
  radius_km?: number
  page?: number
  per_page?: number
}

export interface PaginatedListings {
  data: ScoredListing[]
  meta: {
    current_page: number
    last_page: number
    per_page: number
    total: number
  }
}

export async function searchListings(params: SearchParams = {}): Promise<PaginatedListings> {
  const qs = new URLSearchParams()
  for (const [k, v] of Object.entries(params)) {
    if (v !== undefined && v !== null && v !== "") qs.set(k, String(v))
  }
  const res = await apiFetch(`/v1/listings?${qs.toString()}`)
  return res.json() as Promise<PaginatedListings>
}

export async function getListing(id: string): Promise<ScoredListing> {
  const res = await apiFetch(`/v1/listings/${id}`)
  const data = await res.json() as { data: ScoredListing }
  return data.data
}

export interface Shortlist {
  id: string
  name: string
  listing_ids: string[]
  created_at: string
}

export async function getShortlists(): Promise<Shortlist[]> {
  const res = await apiFetch("/v1/shortlists")
  const data = await res.json() as { data: Shortlist[] }
  return data.data
}

export async function createShortlist(name: string): Promise<Shortlist> {
  const res = await apiFetch("/v1/shortlists", {
    method: "POST",
    body: JSON.stringify({ name }),
  })
  const data = await res.json() as { data: Shortlist }
  return data.data
}

export async function addToShortlist(shortlistId: string, listingId: string): Promise<void> {
  await apiFetch(`/v1/shortlists/${shortlistId}/listings`, {
    method: "POST",
    body: JSON.stringify({ listing_id: listingId }),
  })
}

export async function removeFromShortlist(shortlistId: string, listingId: string): Promise<void> {
  await apiFetch(`/v1/shortlists/${shortlistId}/listings/${listingId}`, { method: "DELETE" })
}
