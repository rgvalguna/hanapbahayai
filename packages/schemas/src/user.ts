import { z } from "zod"
import { PSGCAddress, Point } from "./common.js"

export const BuyerArchetype = z.enum([
  "FirstTimeStarter",
  "OFWRemitter",
  "DualIncomeUpgrader",
  "YoungFamilySettler",
  "InvestorYielder",
  "RetireeDownsizer",
])
export type BuyerArchetype = z.infer<typeof BuyerArchetype>

export const BuyerModifier = z.enum([
  "TightBudget",
  "CommuteCritical",
  "SchoolDistrictDriven",
  "FloodAverse",
  "LiquidityConstrained",
  "HighRiskTolerant",
])
export type BuyerModifier = z.infer<typeof BuyerModifier>

export const RiskTolerance = z.enum(["conservative", "moderate", "aggressive"])
export type RiskTolerance = z.infer<typeof RiskTolerance>

export const PurchasePurpose = z.enum(["primary_residence", "investment_rental", "vacation_home", "retirement_home"])
export type PurchasePurpose = z.infer<typeof PurchasePurpose>

export const CommuteMode = z.enum(["drive", "mrt_lrt", "bus", "motorcycle", "walk"])
export type CommuteMode = z.infer<typeof CommuteMode>

export const User = z.object({
  id: z.string().uuid(),
  email: z.string().email(),
  name: z.string().min(2).max(100),
  avatar_url: z.string().url().optional(),
  phone: z.string().optional(),
  is_ofw: z.boolean().default(false),
  is_verified: z.boolean().default(false),
  is_broker: z.boolean().default(false),
  onboarding_completed: z.boolean().default(false),
  created_at: z.string().datetime(),
})
export type User = z.infer<typeof User>

export const UserFinances = z.object({
  monthly_gross_income_php: z.number().positive(),
  monthly_take_home_php: z.number().positive(),
  co_borrower_income_php: z.number().min(0).default(0),
  existing_monthly_obligations_php: z.number().min(0).default(0),
  available_down_payment_php: z.number().min(0).default(0),
  monthly_savings_capacity_php: z.number().min(0).default(0),
  has_pagibig: z.boolean().default(false),
  pagibig_contribution_months: z.number().int().min(0).default(0),
  employment_type: z.enum(["employed", "self_employed", "ofw", "business_owner", "retired"]),
  employer_name: z.string().optional(),
  years_employed: z.number().min(0).optional(),
  currency: z.enum(["PHP", "USD", "AED", "SAR", "SGD", "HKD"]).default("PHP"),
})
export type UserFinances = z.infer<typeof UserFinances>

export const UserLocation = z.object({
  workplace_location: Point.optional(),
  workplace_address: PSGCAddress.optional(),
  commute_mode: CommuteMode.default("drive"),
  max_commute_minutes: z.number().int().min(10).max(180).default(60),
  preferred_areas: z.array(PSGCAddress).default([]),
  excluded_areas: z.array(PSGCAddress).default([]),
})
export type UserLocation = z.infer<typeof UserLocation>

export const UserPreferences = z.object({
  property_types: z.array(z.enum(["condo", "townhouse", "single_detached", "lot_only"])).default([]),
  min_bedrooms: z.number().int().min(0).default(0),
  min_floor_area_sqm: z.number().positive().optional(),
  tenure_types: z.array(z.enum(["freehold", "leasehold", "rfo", "pre_selling"])).default([]),
  must_have_parking: z.boolean().default(false),
  must_have_pool: z.boolean().default(false),
  pet_friendly: z.boolean().default(false),
  near_schools: z.boolean().default(false),
  children_ages: z.array(z.number().int().min(0).max(25)).default([]),
  risk_tolerance: RiskTolerance.default("moderate"),
  purchase_purpose: PurchasePurpose.default("primary_residence"),
  target_move_in_months: z.number().int().min(0).max(120).optional(),
})
export type UserPreferences = z.infer<typeof UserPreferences>

export const UserProfile = z.object({
  id: z.string().uuid(),
  user_id: z.string().uuid(),
  archetype: BuyerArchetype.optional(),
  modifiers: z.array(BuyerModifier).default([]),
  finances: UserFinances.optional(),
  location: UserLocation.optional(),
  preferences: UserPreferences.optional(),
  onboarding_step: z.number().int().min(0).max(5).default(0),
  profile_score: z.number().min(0).max(100).optional(),
  updated_at: z.string().datetime(),
})
export type UserProfile = z.infer<typeof UserProfile>

// Onboarding wizard steps
export const OnboardingStep1 = z.object({
  is_ofw: z.boolean(),
  employment_type: UserFinances.shape.employment_type,
  monthly_gross_income_php: z.number().positive(),
  co_borrower_income_php: z.number().min(0).default(0),
  existing_monthly_obligations_php: z.number().min(0).default(0),
  has_pagibig: z.boolean(),
  pagibig_contribution_months: z.number().int().min(0).default(0),
  currency: UserFinances.shape.currency,
})
export type OnboardingStep1 = z.infer<typeof OnboardingStep1>

export const OnboardingStep2 = z.object({
  available_down_payment_php: z.number().min(0),
  monthly_savings_capacity_php: z.number().min(0),
  risk_tolerance: RiskTolerance,
  purchase_purpose: PurchasePurpose,
})
export type OnboardingStep2 = z.infer<typeof OnboardingStep2>

export const OnboardingStep3 = z.object({
  workplace_lat: z.number().optional(),
  workplace_lng: z.number().optional(),
  commute_mode: CommuteMode,
  max_commute_minutes: z.number().int().min(10).max(180),
  preferred_area_codes: z.array(z.string()).default([]),
})
export type OnboardingStep3 = z.infer<typeof OnboardingStep3>

export const OnboardingStep4 = z.object({
  property_types: z.array(z.string()),
  min_bedrooms: z.number().int().min(0),
  near_schools: z.boolean(),
  children_ages: z.array(z.number().int()),
  must_have_parking: z.boolean(),
  target_move_in_months: z.number().int().optional(),
})
export type OnboardingStep4 = z.infer<typeof OnboardingStep4>

export const OnboardingPayload = z.object({
  step1: OnboardingStep1,
  step2: OnboardingStep2,
  step3: OnboardingStep3,
  step4: OnboardingStep4,
})
export type OnboardingPayload = z.infer<typeof OnboardingPayload>
