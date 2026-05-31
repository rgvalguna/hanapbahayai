import { z } from "zod"

export const UUIDSchema = z.string().uuid()

export const PaginationQuery = z.object({
  page: z.coerce.number().int().positive().default(1),
  per_page: z.coerce.number().int().min(1).max(100).default(20),
})
export type PaginationQuery = z.infer<typeof PaginationQuery>

export const Point = z.object({
  lat: z.number().min(-90).max(90),
  lng: z.number().min(-180).max(180),
})
export type Point = z.infer<typeof Point>

export const BoundingBox = z.object({
  north: z.number(),
  south: z.number(),
  east: z.number(),
  west: z.number(),
})
export type BoundingBox = z.infer<typeof BoundingBox>

export const PSGCAddress = z.object({
  region_code: z.string(),
  region_name: z.string(),
  province_code: z.string().optional(),
  province_name: z.string().optional(),
  city_muni_code: z.string(),
  city_muni_name: z.string(),
  barangay_code: z.string().optional(),
  barangay_name: z.string().optional(),
  zip_code: z.string().optional(),
  full_address: z.string(),
})
export type PSGCAddress = z.infer<typeof PSGCAddress>

export const SortOrder = z.enum(["asc", "desc"])
export type SortOrder = z.infer<typeof SortOrder>

export const ApiSuccess = <T extends z.ZodTypeAny>(dataSchema: T) =>
  z.object({
    success: z.literal(true),
    data: dataSchema,
    meta: z
      .object({
        page: z.number().optional(),
        per_page: z.number().optional(),
        total: z.number().optional(),
        last_page: z.number().optional(),
      })
      .optional(),
  })

export const ApiError = z.object({
  success: z.literal(false),
  message: z.string(),
  errors: z.record(z.string(), z.array(z.string())).optional(),
  code: z.string().optional(),
})
export type ApiError = z.infer<typeof ApiError>
