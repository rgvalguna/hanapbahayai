"use client"

import Link from "next/link"
import { usePathname } from "next/navigation"
import {
  LayoutDashboard,
  Search,
  MessageSquareText,
  Calculator,
  Heart,
  Settings,
  X,
} from "lucide-react"
import { cn } from "@/lib/utils"
import { useUIStore } from "@/lib/store/ui"
import { useEffect } from "react"

const navItems = [
  { href: "/dashboard", label: "Dashboard", icon: LayoutDashboard },
  { href: "/listings", label: "Browse", icon: Search },
  { href: "/advisor", label: "AI Advisor", icon: MessageSquareText },
  { href: "/financial", label: "Calculator", icon: Calculator },
  { href: "/shortlists", label: "Shortlists", icon: Heart },
  { href: "/profile", label: "Profile", icon: Settings },
]

export function MobileSidebar() {
  const open = useUIStore((s) => s.sidebarOpen)
  const setSidebarOpen = useUIStore((s) => s.setSidebarOpen)
  const pathname = usePathname()

  // Close on route change
  useEffect(() => {
    setSidebarOpen(false)
  }, [pathname, setSidebarOpen])

  // Close on Escape
  useEffect(() => {
    if (!open) return
    function onKeyDown(e: KeyboardEvent) {
      if (e.key === "Escape") setSidebarOpen(false)
    }
    document.addEventListener("keydown", onKeyDown)
    return () => document.removeEventListener("keydown", onKeyDown)
  }, [open, setSidebarOpen])

  return (
    <>
      {/* Backdrop */}
      <div
        aria-hidden="true"
        onClick={() => setSidebarOpen(false)}
        className={cn(
          "fixed inset-0 z-40 bg-black/40 transition-opacity lg:hidden",
          open ? "opacity-100" : "pointer-events-none opacity-0",
        )}
      />

      {/* Drawer */}
      <aside
        aria-label="Navigation menu"
        className={cn(
          "fixed inset-y-0 left-0 z-50 flex w-64 flex-col border-r border-[--color-border] bg-white transition-transform lg:hidden",
          open ? "translate-x-0" : "-translate-x-full",
        )}
      >
        {/* Header */}
        <div className="flex h-14 items-center justify-between border-b border-[--color-border] px-4">
          <Link
            href="/dashboard"
            className="flex items-center gap-2"
            onClick={() => setSidebarOpen(false)}
          >
            <div className="flex h-7 w-7 items-center justify-center rounded-lg bg-[--color-brand-600] text-xs font-bold text-white">
              H
            </div>
            <span className="text-sm font-semibold text-gray-900">
              HanapBahay <span className="text-[--color-brand-600]">AI</span>
            </span>
          </Link>
          <button
            onClick={() => setSidebarOpen(false)}
            className="rounded-md p-1.5 text-gray-500 hover:bg-gray-100"
            aria-label="Close menu"
          >
            <X className="h-5 w-5" />
          </button>
        </div>

        {/* Nav */}
        <nav className="flex flex-1 flex-col gap-0.5 overflow-y-auto p-3">
          {navItems.map(({ href, label, icon: Icon }) => {
            const active = pathname === href || pathname.startsWith(href + "/")
            return (
              <Link
                key={href}
                href={href}
                className={cn(
                  "flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm transition-colors",
                  active
                    ? "bg-[--color-brand-50] font-medium text-[--color-brand-700]"
                    : "text-gray-600 hover:bg-gray-100 hover:text-gray-900",
                )}
              >
                <Icon className="h-4 w-4 flex-shrink-0" />
                {label}
              </Link>
            )
          })}
        </nav>

        {/* Footer */}
        <div className="border-t border-[--color-border] p-3">
          <p className="px-3 text-[10px] text-gray-400">
            Advisory only — not a licensed financial product.
          </p>
        </div>
      </aside>
    </>
  )
}
