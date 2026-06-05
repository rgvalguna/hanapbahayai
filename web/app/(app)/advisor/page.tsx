"use client"

import { useState, useRef, useEffect, useCallback } from "react"
import { Send, Sparkles, RotateCcw } from "lucide-react"
import { cn } from "@/lib/utils"

interface Message {
  id: string
  role: "user" | "assistant"
  content: string
  isStreaming?: boolean
}

const API_BASE = process.env.NEXT_PUBLIC_API_URL ?? "http://localhost:8000"

async function ensureConsultation(): Promise<string> {
  const res = await fetch(`${API_BASE}/api/v1/advisor/consultations`, {
    method: "POST",
    headers: { "Content-Type": "application/json", Accept: "application/json" },
    body: JSON.stringify({ title: "New consultation" }),
    credentials: "include",
  })
  if (!res.ok) throw new Error(`Failed to create consultation: ${res.status}`)
  const json = await res.json() as { data: { id: string } }
  return json.data.id
}

let consultationId: string | null = null

function MessageBubble({ message }: { message: Message }) {
  const isUser = message.role === "user"
  return (
    <div className={cn("flex gap-3", isUser && "flex-row-reverse")}>
      {/* Avatar */}
      <div
        className={cn(
          "mt-1 flex h-7 w-7 flex-shrink-0 items-center justify-center rounded-full text-xs font-bold",
          isUser
            ? "bg-[--color-brand-100] text-[--color-brand-700]"
            : "bg-[--color-brand-600] text-white",
        )}
      >
        {isUser ? "U" : <Sparkles className="h-3.5 w-3.5" />}
      </div>

      {/* Bubble */}
      <div
        className={cn(
          "max-w-[80%] rounded-2xl px-4 py-3 text-sm leading-relaxed",
          isUser
            ? "rounded-tr-sm bg-[--color-brand-600] text-white"
            : "rounded-tl-sm border border-[--color-border] bg-white text-gray-800",
        )}
      >
        {message.content}
        {message.isStreaming && (
          <span className="ml-1 inline-block h-3 w-0.5 animate-[stream-text_0.8s_ease-in-out_infinite] bg-current opacity-70" />
        )}
      </div>
    </div>
  )
}

export default function AdvisorPage() {
  const [messages, setMessages] = useState<Message[]>([
    {
      id: "welcome",
      role: "assistant",
      content:
        "Hello! I'm your AI real estate advisor. I'm here to help you find the right property in the Philippines — one that fits your budget, lifestyle, and long-term goals. What are you looking for today?",
    },
  ])
  const [input, setInput] = useState("")
  const [loading, setLoading] = useState(false)
  const bottomRef = useRef<HTMLDivElement>(null)
  const abortRef = useRef<AbortController | null>(null)

  useEffect(() => {
    bottomRef.current?.scrollIntoView({ behavior: "smooth" })
  }, [messages])

  const send = useCallback(async () => {
    const text = input.trim()
    if (!text || loading) return

    setInput("")
    setLoading(true)

    const userMsg: Message = { id: crypto.randomUUID(), role: "user", content: text }
    const assistantMsgId = crypto.randomUUID()

    setMessages((prev) => [
      ...prev,
      userMsg,
      { id: assistantMsgId, role: "assistant", content: "", isStreaming: true },
    ])

    abortRef.current = new AbortController()

    try {
      // Create a consultation session on the first message
      if (!consultationId) {
        consultationId = await ensureConsultation()
      }

      const url = `${API_BASE}/api/v1/advisor/consultations/${consultationId}/messages`

      const res = await fetch(url, {
        method: "POST",
        headers: { "Content-Type": "application/json", Accept: "text/event-stream" },
        body: JSON.stringify({ message: text }),
        credentials: "include",
        signal: abortRef.current.signal,
      })

      if (!res.ok || !res.body) throw new Error(`Server error ${res.status}`)

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
          if (line.startsWith("data: ")) {
            const data = line.slice(6).trim()
            if (data === "[DONE]") break
            try {
              const parsed = JSON.parse(data) as {
                type?: string
                delta?: string
                tool_name?: string
                error?: string
              }
              if (parsed.type === "delta" || parsed.delta) {
                setMessages((prev) =>
                  prev.map((m) =>
                    m.id === assistantMsgId
                      ? { ...m, content: m.content + (parsed.delta ?? "") }
                      : m,
                  ),
                )
              } else if (parsed.type === "error") {
                throw new Error(parsed.error ?? "Advisor error")
              }
              // tool_call events are informational; no UI update needed
            } catch {
              // ignore non-JSON SSE lines
            }
          }
        }
      }
    } catch (err) {
      if ((err as Error).name !== "AbortError") {
        setMessages((prev) =>
          prev.map((m) =>
            m.id === assistantMsgId
              ? {
                  ...m,
                  content: "Sorry, I encountered an error. Please try again.",
                  isStreaming: false,
                }
              : m,
          ),
        )
      }
    } finally {
      setMessages((prev) =>
        prev.map((m) =>
          m.id === assistantMsgId ? { ...m, isStreaming: false } : m,
        ),
      )
      setLoading(false)
    }
  }, [input, loading])

  function handleReset() {
    abortRef.current?.abort()
    consultationId = null
    setMessages([
      {
        id: "welcome",
        role: "assistant",
        content: "Starting a new consultation. What are you looking for today?",
      },
    ])
    setInput("")
    setLoading(false)
  }

  return (
    <div className="flex h-[calc(100vh-3.5rem-3rem)] flex-col">
      {/* Header */}
      <div className="mb-4 flex items-center justify-between">
        <div>
          <h1 className="flex items-center gap-2 text-2xl font-semibold text-gray-900">
            <Sparkles className="h-5 w-5 text-[--color-brand-600]" />
            AI Advisor
          </h1>
          <p className="mt-0.5 text-sm text-gray-500">
            Fiduciary-first advice. Your long-term wellbeing, not a commission.
          </p>
        </div>
        <button
          onClick={handleReset}
          className="flex items-center gap-1.5 rounded-lg border border-[--color-border] px-3 py-1.5 text-xs text-gray-500 transition hover:bg-gray-50"
        >
          <RotateCcw className="h-3.5 w-3.5" />
          New chat
        </button>
      </div>

      {/* Messages */}
      <div className="flex-1 space-y-4 overflow-y-auto rounded-2xl border border-[--color-border] bg-[--color-surface] p-5">
        {messages.map((msg) => (
          <MessageBubble key={msg.id} message={msg} />
        ))}
        <div ref={bottomRef} />
      </div>

      {/* Input */}
      <div className="mt-4 flex gap-3">
        <input
          type="text"
          value={input}
          onChange={(e) => setInput(e.target.value)}
          onKeyDown={(e) => {
            if (e.key === "Enter" && !e.shiftKey) {
              e.preventDefault()
              void send()
            }
          }}
          placeholder="Ask about a property, your budget, flood risk, or anything else…"
          disabled={loading}
          className="flex-1 rounded-xl border border-[--color-border] px-4 py-3 text-sm placeholder-gray-400 focus:border-[--color-brand-500] focus:outline-none focus:ring-2 focus:ring-[--color-brand-500]/20 disabled:opacity-60"
        />
        <button
          onClick={() => void send()}
          disabled={!input.trim() || loading}
          className="flex h-12 w-12 items-center justify-center rounded-xl bg-[--color-brand-600] text-white transition hover:bg-[--color-brand-700] disabled:opacity-40"
          aria-label="Send message"
        >
          <Send className="h-4 w-4" />
        </button>
      </div>
    </div>
  )
}
