import { z } from "zod"
import { BuyerArchetype } from "./user.js"

export const ScoreDimension = z.enum([
  "affordability",
  "commute",
  "flood_safety",
  "education",
  "healthcare",
  "safety",
  "investment_potential",
  "livability",
  "internet",
  "developer_reputation",
])
export type ScoreDimension = z.infer<typeof ScoreDimension>

export const ScoreWeights = z.record(ScoreDimension, z.number().min(0).max(1))
export type ScoreWeights = z.infer<typeof ScoreWeights>

export const DimensionScore = z.object({
  dimension: ScoreDimension,
  raw_score: z.number().min(0).max(100),
  weighted_score: z.number().min(0).max(100),
  weight: z.number().min(0).max(1),
  explanation: z.string(),
  data_points: z.record(z.string(), z.unknown()).optional(),
})
export type DimensionScore = z.infer<typeof DimensionScore>

export const ScoreWarning = z.object({
  type: z.enum(["hard_exclude", "strong_caution", "soft_caution"]),
  dimension: ScoreDimension,
  message: z.string(),
})
export type ScoreWarning = z.infer<typeof ScoreWarning>

export const ScoredProperty = z.object({
  listing_id: z.string().uuid(),
  total_score: z.number().min(0).max(100),
  archetype: BuyerArchetype,
  dimensions: z.array(DimensionScore),
  warnings: z.array(ScoreWarning),
  rank: z.number().int().positive().optional(),
  scored_at: z.string().datetime(),
})
export type ScoredProperty = z.infer<typeof ScoredProperty>

export const ArchetypeWeightsMap = z.record(BuyerArchetype, ScoreWeights)
export type ArchetypeWeightsMap = z.infer<typeof ArchetypeWeightsMap>

// Default archetype weights (must sum to 1.0 each)
export const DEFAULT_ARCHETYPE_WEIGHTS: ArchetypeWeightsMap = {
  FirstTimeStarter: {
    affordability: 0.30,
    commute: 0.15,
    flood_safety: 0.10,
    education: 0.10,
    healthcare: 0.08,
    safety: 0.10,
    investment_potential: 0.05,
    livability: 0.07,
    internet: 0.03,
    developer_reputation: 0.02,
  },
  OFWRemitter: {
    affordability: 0.25,
    commute: 0.05,
    flood_safety: 0.12,
    education: 0.12,
    healthcare: 0.10,
    safety: 0.15,
    investment_potential: 0.10,
    livability: 0.05,
    internet: 0.03,
    developer_reputation: 0.03,
  },
  DualIncomeUpgrader: {
    affordability: 0.20,
    commute: 0.20,
    flood_safety: 0.08,
    education: 0.10,
    healthcare: 0.08,
    safety: 0.10,
    investment_potential: 0.10,
    livability: 0.08,
    internet: 0.04,
    developer_reputation: 0.02,
  },
  YoungFamilySettler: {
    affordability: 0.20,
    commute: 0.12,
    flood_safety: 0.10,
    education: 0.20,
    healthcare: 0.12,
    safety: 0.12,
    investment_potential: 0.05,
    livability: 0.05,
    internet: 0.02,
    developer_reputation: 0.02,
  },
  InvestorYielder: {
    affordability: 0.10,
    commute: 0.10,
    flood_safety: 0.08,
    education: 0.05,
    healthcare: 0.05,
    safety: 0.07,
    investment_potential: 0.30,
    livability: 0.15,
    internet: 0.05,
    developer_reputation: 0.05,
  },
  RetireeDownsizer: {
    affordability: 0.20,
    commute: 0.05,
    flood_safety: 0.12,
    education: 0.03,
    healthcare: 0.25,
    safety: 0.15,
    investment_potential: 0.05,
    livability: 0.10,
    internet: 0.03,
    developer_reputation: 0.02,
  },
}
