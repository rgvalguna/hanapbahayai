import { z } from "zod"
import { Point, PSGCAddress, PaginationQuery, SortOrder } from "./common.js"

export const PropertyType = z.enum([
  "condo",
  "townhouse",
  "single_detached",
  "lot_only",
  "commercial",
  "warehouse",
])
export type PropertyType = z.infer<typeof PropertyType>

export const TenureType = z.enum(["freehold", "leasehold", "rfo", "pre_selling"])
export type TenureType = z.infer<typeof TenureType>

export const ListingStatus = z.enum(["active", "under_review", "sold", "off_market", "archived"])
export type ListingStatus = z.infer<typeof ListingStatus>

export const ListingSource = z.enum([
  "pagibig_ropa",
  "lamudi",
  "property24",
  "broker_manual",
  "developer_feed",
  "admin_import",
])
export type ListingSource = z.infer<typeof ListingSource>

export const FraudFlag = z.enum([
  "duplicate_photo",
  "price_anomaly",
  "address_mismatch",
  "no_title_mentioned",
  "phantom_developer",
  "undervalued_suspicious",
])
export type FraudFlag = z.infer<typeof FraudFlag>

export const Listing = z.object({
  id: z.string().uuid(),
  external_id: z.string().optional(),
  source: ListingSource,
  status: ListingStatus,
  property_type: PropertyType,
  tenure: TenureType,

  title: z.string().min(10).max(200),
  description: z.string().optional(),
  slug: z.string(),

  price_php: z.number().positive(),
  price_per_sqm_php: z.number().positive().optional(),
  floor_area_sqm: z.number().positive().optional(),
  lot_area_sqm: z.number().positive().optional(),
  bedrooms: z.number().int().min(0).max(20).optional(),
  bathrooms: z.number().min(0).max(20).optional(),
  parking_slots: z.number().int().min(0).max(10).optional(),
  floor_number: z.number().int().optional(),
  total_floors: z.number().int().optional(),
  year_built: z.number().int().optional(),

  location: Point,
  address: PSGCAddress,
  developer_id: z.string().uuid().optional(),
  broker_id: z.string().uuid().optional(),

  photos: z.array(z.string().url()).default([]),
  virtual_tour_url: z.string().url().optional(),
  floor_plan_url: z.string().url().optional(),

  fraud_flags: z.array(FraudFlag).default([]),
  fraud_score: z.number().min(0).max(1).optional(),
  is_verified: z.boolean().default(false),

  score_cache: z.record(z.string(), z.number()).optional(),
  amenity_tags: z.array(z.string()).default([]),

  created_at: z.string().datetime(),
  updated_at: z.string().datetime(),
  published_at: z.string().datetime().optional(),
})
export type Listing = z.infer<typeof Listing>

export const EnrichedListing = Listing.extend({
  developer_name: z.string().optional(),
  broker_name: z.string().optional(),
  nearby_schools: z.number().int().optional(),
  nearby_hospitals: z.number().int().optional(),
  flood_zone: z.enum(["none", "low", "medium", "high"]).optional(),
  commute_estimate_minutes: z.number().optional(),
  three_year_appreciation_pct: z.number().optional(),
  rental_yield_pct: z.number().optional(),
})
export type EnrichedListing = z.infer<typeof EnrichedListing>

export const CreateListing = Listing.omit({
  id: true,
  slug: true,
  status: true,
  fraud_flags: true,
  fraud_score: true,
  is_verified: true,
  score_cache: true,
  created_at: true,
  updated_at: true,
  published_at: true,
})
export type CreateListing = z.infer<typeof CreateListing>

export const ListingSearchParams = PaginationQuery.extend({
  q: z.string().optional(),
  property_type: z.array(PropertyType).optional(),
  tenure: z.array(TenureType).optional(),
  min_price: z.coerce.number().positive().optional(),
  max_price: z.coerce.number().positive().optional(),
  min_area_sqm: z.coerce.number().positive().optional(),
  max_area_sqm: z.coerce.number().positive().optional(),
  bedrooms_min: z.coerce.number().int().min(0).optional(),
  region_code: z.string().optional(),
  city_muni_code: z.string().optional(),
  barangay_code: z.string().optional(),
  near_lat: z.coerce.number().optional(),
  near_lng: z.coerce.number().optional(),
  radius_km: z.coerce.number().positive().max(50).optional(),
  sort_by: z.enum(["price", "area", "score", "published_at", "price_per_sqm"]).optional(),
  sort_order: SortOrder.optional(),
  verified_only: z.coerce.boolean().optional(),
})
export type ListingSearchParams = z.infer<typeof ListingSearchParams>
