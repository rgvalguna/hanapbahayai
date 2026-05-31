import { z } from "zod"
import { ScoredProperty } from "./scoring.js"
import { FinancialSimulation } from "./financial.js"

export const MessageRole = z.enum(["user", "assistant", "system", "tool"])
export type MessageRole = z.infer<typeof MessageRole>

export const ClaudeModel = z.enum([
  "claude-opus-4-7-20251101",
  "claude-haiku-4-5-20251101",
  "claude-sonnet-4-6-20251101",
])
export type ClaudeModel = z.infer<typeof ClaudeModel>

export const ConsultationMessage = z.object({
  id: z.string().uuid(),
  consultation_id: z.string().uuid(),
  role: MessageRole,
  content: z.string(),
  model: ClaudeModel.optional(),
  tool_calls: z.array(z.unknown()).optional(),
  tool_results: z.array(z.unknown()).optional(),
  input_tokens: z.number().int().optional(),
  output_tokens: z.number().int().optional(),
  cache_read_tokens: z.number().int().optional(),
  cache_write_tokens: z.number().int().optional(),
  created_at: z.string().datetime(),
})
export type ConsultationMessage = z.infer<typeof ConsultationMessage>

export const Consultation = z.object({
  id: z.string().uuid(),
  user_id: z.string().uuid(),
  title: z.string().optional(),
  model: ClaudeModel.default("claude-opus-4-7-20251101"),
  messages: z.array(ConsultationMessage).default([]),
  context_hash: z.string().optional(),
  is_active: z.boolean().default(true),
  started_at: z.string().datetime(),
  last_message_at: z.string().datetime().optional(),
})
export type Consultation = z.infer<typeof Consultation>

export const Recommendation = z.object({
  id: z.string().uuid(),
  user_id: z.string().uuid(),
  consultation_id: z.string().uuid().optional(),
  listing_id: z.string().uuid(),
  scored_property: ScoredProperty,
  financial_snapshot: FinancialSimulation.optional(),
  claude_rationale: z.string(),
  rank: z.number().int().positive(),
  is_dismissed: z.boolean().default(false),
  created_at: z.string().datetime(),
})
export type Recommendation = z.infer<typeof Recommendation>

export const Shortlist = z.object({
  id: z.string().uuid(),
  user_id: z.string().uuid(),
  name: z.string().default("My Shortlist"),
  listing_ids: z.array(z.string().uuid()).default([]),
  created_at: z.string().datetime(),
  updated_at: z.string().datetime(),
})
export type Shortlist = z.infer<typeof Shortlist>

export const SendMessage = z.object({
  consultation_id: z.string().uuid().optional(),
  message: z.string().min(1).max(4000),
  model: ClaudeModel.optional(),
  context: z
    .object({
      active_listing_id: z.string().uuid().optional(),
      compare_listing_ids: z.array(z.string().uuid()).max(4).optional(),
    })
    .optional(),
})
export type SendMessage = z.infer<typeof SendMessage>

export const StreamEvent = z.discriminatedUnion("type", [
  z.object({ type: z.literal("text_delta"), delta: z.string() }),
  z.object({ type: z.literal("tool_call_start"), tool_name: z.string(), tool_id: z.string() }),
  z.object({ type: z.literal("tool_call_result"), tool_id: z.string(), result: z.unknown() }),
  z.object({ type: z.literal("message_done"), message_id: z.string(), usage: z.record(z.string(), z.number()) }),
  z.object({ type: z.literal("error"), message: z.string() }),
])
export type StreamEvent = z.infer<typeof StreamEvent>
