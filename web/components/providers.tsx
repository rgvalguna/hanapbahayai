"use client"

import { useState, useEffect } from "react"
import { QueryClient, QueryClientProvider } from "@tanstack/react-query"
import { useAuthStore } from "@/lib/store/auth"
import { getMe } from "@/lib/api/auth"

function AuthHydrator() {
  const setUser = useAuthStore((s) => s.setUser)
  const clearUser = useAuthStore((s) => s.clearUser)

  useEffect(() => {
    getMe()
      .then((user) => setUser(user))
      .catch(() => clearUser())
  }, [setUser, clearUser])

  return null
}

export function Providers({ children }: { children: React.ReactNode }) {
  const [queryClient] = useState(
    () =>
      new QueryClient({
        defaultOptions: {
          queries: {
            staleTime: 60_000,
            retry: 1,
          },
        },
      }),
  )

  return (
    <QueryClientProvider client={queryClient}>
      <AuthHydrator />
      {children}
    </QueryClientProvider>
  )
}
