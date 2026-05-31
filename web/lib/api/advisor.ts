import { apiFetch } from "./client"

const API_BASE = process.env.NEXT_PUBLIC_API_URL ?? "http://localhost:8000"

export interface ConsultationMessage {
  id: string
  role: "user" | "assistant"
  content: string
  created_at: string
}

export interface Consultation {
  id: string
  title: string
  model: string
  created_at: string
  messages?: ConsultationMessage[]
}

export async function getConsultations(): Promise<{ data: Consultation[] }> {
  const res = await apiFetch("/v1/advisor/consultations")
  return res.json()
}

export async function createConsultation(title?: string): Promise<Consultation> {
  const res = await apiFetch("/v1/advisor/consultations", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ title }),
  })
  const data = await res.json() as { data: Consultation }
  return data.data
}

export async function getConsultation(consultationId: string): Promise<Consultation> {
  const res = await apiFetch(`/v1/advisor/consultations/${consultationId}`)
  const data = await res.json() as { data: Consultation }
  return data.data
}

export async function deleteConsultation(consultationId: string): Promise<void> {
  await apiFetch(`/v1/advisor/consultations/${consultationId}`, { method: "DELETE" })
}

export interface Recommendation {
  id: string
  listing: unknown
  score_total: number
  scores: Record<string, number>
  rationale: string
  warnings: Array<{ severity: string; type: string; message: string }>
  created_at: string
}

export async function getRecommendations(): Promise<{ data: Recommendation[] }> {
  const res = await apiFetch("/v1/advisor/recommendations")
  return res.json()
}

export interface StreamOptions {
  message: string
  listingIds?: string[]
  consultationId?: string | null
  signal?: AbortSignal
  onDelta: (delta: string) => void
  onConsultationId: (id: string) => void
  onDone: () => void
  onError: (message: string) => void
}

export async function streamAdvisorMessage({
  message,
  listingIds,
  consultationId,
  signal,
  onDelta,
  onConsultationId,
  onDone,
  onError,
}: StreamOptions): Promise<void> {
  // Resolve or create a consultation before streaming
  let resolvedId = consultationId
  if (!resolvedId) {
    try {
      const consultation = await createConsultation()
      resolvedId = consultation.id
      onConsultationId(resolvedId)
    } catch {
      onError("Failed to start consultation. Please try again.")
      return
    }
  }

  const url = `${API_BASE}/api/v1/advisor/consultations/${resolvedId}/messages`

  let res: Response
  try {
    res = await fetch(url, {
      method: "POST",
      headers: { "Content-Type": "application/json", Accept: "text/event-stream" },
      body: JSON.stringify({ message, listing_ids: listingIds ?? [] }),
      credentials: "include",
      signal,
    })
  } catch (err) {
    if ((err as Error).name !== "AbortError") {
      onError("Connection failed. Please try again.")
    }
    return
  }

  if (!res.ok || !res.body) {
    onError(`Server error ${res.status}`)
    return
  }

  const reader = res.body.getReader()
  const decoder = new TextDecoder()
  let buffer = ""

  while (true) {
    const { done, value } = await reader.read()
    if (done) break

    buffer += decoder.decode(value, { stream: true })
    const lines = buffer.split("\n")
    buffer = lines.pop() ?? ""

    for (const line of lines) {
      if (!line.startsWith("data: ")) continue
      const raw = line.slice(6).trim()
      try {
        const parsed = JSON.parse(raw) as { type: string; delta?: string }
        if (parsed.type === "done") {
          onDone()
          return
        }
        if (parsed.type === "delta" && parsed.delta) {
          onDelta(parsed.delta)
        }
      } catch {
        // ignore malformed SSE lines
      }
    }
  }

  onDone()
}
