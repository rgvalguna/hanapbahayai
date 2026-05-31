const API_BASE = process.env.NEXT_PUBLIC_API_URL ?? "http://localhost:8000"

let csrfToken: string | null = null

async function fetchCsrfToken(): Promise<void> {
  await fetch(`${API_BASE}/sanctum/csrf-cookie`, {
    credentials: "include",
  })
  // Laravel sets XSRF-TOKEN cookie; extract it
  const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/)
  csrfToken = match ? decodeURIComponent(match[1]) : null
}

export async function apiFetch(
  path: string,
  init: RequestInit = {},
): Promise<Response> {
  const url = `${API_BASE}/api${path}`
  const method = (init.method ?? "GET").toUpperCase()
  const mutating = ["POST", "PUT", "PATCH", "DELETE"].includes(method)

  if (mutating && !csrfToken) {
    await fetchCsrfToken()
  }

  const headers = new Headers(init.headers)
  headers.set("Accept", "application/json")
  if (!headers.has("Content-Type") && init.body) {
    headers.set("Content-Type", "application/json")
  }
  if (mutating && csrfToken) {
    headers.set("X-XSRF-TOKEN", csrfToken)
  }

  const res = await fetch(url, {
    ...init,
    headers,
    credentials: "include",
  })

  if (res.status === 419) {
    // CSRF token expired — refresh and retry once
    csrfToken = null
    await fetchCsrfToken()
    headers.set("X-XSRF-TOKEN", csrfToken ?? "")
    return fetch(url, { ...init, headers, credentials: "include" })
  }

  if (!res.ok) {
    const body = await res.clone().json().catch(() => ({})) as Record<string, unknown>
    const message =
      (body.message as string | undefined) ??
      (body.error as string | undefined) ??
      `HTTP ${res.status}`
    throw new Error(message)
  }

  return res
}
