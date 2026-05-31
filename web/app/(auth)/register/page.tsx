"use client"

import { useState } from "react"
import Link from "next/link"
import { useRouter } from "next/navigation"
import { useAuthStore } from "@/lib/store/auth"
import { apiFetch } from "@/lib/api/client"

export default function RegisterPage() {
  const router = useRouter()
  const setUser = useAuthStore((s) => s.setUser)

  const [name, setName] = useState("")
  const [email, setEmail] = useState("")
  const [password, setPassword] = useState("")
  const [error, setError] = useState<string | null>(null)
  const [loading, setLoading] = useState(false)

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault()
    setError(null)
    setLoading(true)

    try {
      const res = await apiFetch("/v1/auth/register", {
        method: "POST",
        body: JSON.stringify({ name, email, password, password_confirmation: password }),
      })
      const data = (await res.json()) as { data: { user: unknown } }
      setUser(data.data.user as Parameters<typeof setUser>[0])
      // Direct to onboarding wizard for new users
      router.push("/profile/onboarding")
    } catch (err) {
      setError(err instanceof Error ? err.message : "Registration failed. Please try again.")
    } finally {
      setLoading(false)
    }
  }

  return (
    <div className="rounded-2xl border border-[--color-border] bg-white px-8 py-10 shadow-[--shadow-card]">
      <h1 className="mb-1 text-2xl font-semibold text-gray-900">Create your account</h1>
      <p className="mb-8 text-sm text-gray-500">
        Already have an account?{" "}
        <Link href="/login" className="font-medium text-[--color-brand-600] hover:underline">
          Sign in
        </Link>
      </p>

      <form onSubmit={(e) => void handleSubmit(e)} className="space-y-5">
        {error && (
          <div className="rounded-lg bg-red-50 px-4 py-3 text-sm text-red-700">{error}</div>
        )}

        <div>
          <label htmlFor="name" className="mb-1.5 block text-sm font-medium text-gray-700">
            Full name
          </label>
          <input
            id="name"
            type="text"
            autoComplete="name"
            required
            value={name}
            onChange={(e) => setName(e.target.value)}
            className="w-full rounded-lg border border-[--color-border] px-3.5 py-2.5 text-sm placeholder-gray-400 focus:border-[--color-brand-500] focus:outline-none focus:ring-2 focus:ring-[--color-brand-500]/20"
            placeholder="Juan dela Cruz"
          />
        </div>

        <div>
          <label htmlFor="email" className="mb-1.5 block text-sm font-medium text-gray-700">
            Email address
          </label>
          <input
            id="email"
            type="email"
            autoComplete="email"
            required
            value={email}
            onChange={(e) => setEmail(e.target.value)}
            className="w-full rounded-lg border border-[--color-border] px-3.5 py-2.5 text-sm placeholder-gray-400 focus:border-[--color-brand-500] focus:outline-none focus:ring-2 focus:ring-[--color-brand-500]/20"
            placeholder="juan@example.com"
          />
        </div>

        <div>
          <label htmlFor="password" className="mb-1.5 block text-sm font-medium text-gray-700">
            Password
          </label>
          <input
            id="password"
            type="password"
            autoComplete="new-password"
            required
            minLength={8}
            value={password}
            onChange={(e) => setPassword(e.target.value)}
            className="w-full rounded-lg border border-[--color-border] px-3.5 py-2.5 text-sm placeholder-gray-400 focus:border-[--color-brand-500] focus:outline-none focus:ring-2 focus:ring-[--color-brand-500]/20"
            placeholder="At least 8 characters"
          />
        </div>

        <button
          type="submit"
          disabled={loading}
          className="w-full rounded-lg bg-[--color-brand-600] py-3 text-sm font-semibold text-white transition hover:bg-[--color-brand-700] disabled:opacity-60"
        >
          {loading ? "Creating account…" : "Create account"}
        </button>

        <p className="text-center text-xs text-gray-400">
          By signing up you agree to our{" "}
          <Link href="/terms" className="underline hover:text-gray-600">
            Terms
          </Link>{" "}
          and{" "}
          <Link href="/privacy" className="underline hover:text-gray-600">
            Privacy Policy
          </Link>
          .
        </p>
      </form>
    </div>
  )
}
