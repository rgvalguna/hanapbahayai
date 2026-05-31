"use client"

import Link from "next/link"
import { useRouter } from "next/navigation"
import { Bell, MessageSquareText, LogOut, Menu } from "lucide-react"
import { useAuthStore } from "@/lib/store/auth"
import { useUIStore } from "@/lib/store/ui"
import { apiFetch } from "@/lib/api/client"

export function Navbar() {
  const user = useAuthStore((s) => s.user)
  const clearUser = useAuthStore((s) => s.clearUser)
  const toggleSidebar = useUIStore((s) => s.toggleSidebar)
  const toggleAdvisor = useUIStore((s) => s.toggleAdvisor)
  const router = useRouter()

  async function handleLogout() {
    await apiFetch("/v1/auth/logout", { method: "POST" }).catch(() => null)
    clearUser()
    router.push("/login")
  }

  return (
    <header className="flex h-14 items-center gap-3 border-b border-[--color-border] bg-white px-4">
      {/* Mobile menu toggle */}
      <button
        onClick={toggleSidebar}
        className="rounded-md p-1.5 text-gray-500 hover:bg-gray-100 lg:hidden"
        aria-label="Open menu"
      >
        <Menu className="h-5 w-5" />
      </button>

      {/* Mobile logo */}
      <Link href="/dashboard" className="flex items-center gap-2 lg:hidden">
        <div className="flex h-6 w-6 items-center justify-center rounded-md bg-[--color-brand-600] text-[10px] font-bold text-white">
          H
        </div>
        <span className="text-sm font-semibold">HanapBahay AI</span>
      </Link>

      <div className="flex-1" />

      {/* Actions */}
      <div className="flex items-center gap-1">
        <button
          onClick={toggleAdvisor}
          className="flex items-center gap-1.5 rounded-lg bg-[--color-brand-600] px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-[--color-brand-700]"
          aria-label="Open AI advisor"
        >
          <MessageSquareText className="h-3.5 w-3.5" />
          <span className="hidden sm:inline">Ask AI</span>
        </button>

        <button
          className="rounded-md p-1.5 text-gray-500 hover:bg-gray-100"
          aria-label="Notifications"
        >
          <Bell className="h-4.5 w-4.5" />
        </button>

        {user && (
          <div className="flex items-center gap-2 pl-2">
            <div className="flex h-7 w-7 items-center justify-center rounded-full bg-[--color-brand-100] text-xs font-semibold text-[--color-brand-700]">
              {user.name.charAt(0).toUpperCase()}
            </div>
            <span className="hidden text-sm font-medium text-gray-700 sm:block">
              {user.name.split(" ")[0]}
            </span>
            <button
              onClick={() => void handleLogout()}
              className="rounded-md p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-700"
              aria-label="Log out"
            >
              <LogOut className="h-4 w-4" />
            </button>
          </div>
        )}
      </div>
    </header>
  )
}
