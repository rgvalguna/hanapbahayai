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
  ChevronRight,
} from "lucide-react"
import { cn } from "@/lib/utils"

const navItems = [
  { href: "/dashboard", label: "Dashboard", icon: LayoutDashboard },
  { href: "/listings", label: "Browse", icon: Search },
  { href: "/advisor", label: "AI Advisor", icon: MessageSquareText },
  { href: "/financial", label: "Calculator", icon: Calculator },
  { href: "/shortlists", label: "Shortlists", icon: Heart },
  { href: "/profile", label: "Profile", icon: Settings },
]

export function Sidebar() {
  const pathname = usePathname()

  return (
    <aside className="hidden w-60 flex-shrink-0 flex-col border-r border-[--color-border] bg-white lg:flex">
      {/* Logo */}
      <div className="flex h-14 items-center border-b border-[--color-border] px-5">
        <Link href="/dashboard" className="flex items-center gap-2">
          <div className="flex h-7 w-7 items-center justify-center rounded-lg bg-[--color-brand-600] text-xs font-bold text-white">
            H
          </div>
          <span className="text-sm font-semibold text-gray-900">
            HanapBahay <span className="text-[--color-brand-600]">AI</span>
          </span>
        </Link>
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
                "flex items-center gap-3 rounded-lg px-3 py-2 text-sm transition-colors",
                active
                  ? "bg-[--color-brand-50] font-medium text-[--color-brand-700]"
                  : "text-gray-600 hover:bg-gray-100 hover:text-gray-900",
              )}
            >
              <Icon className="h-4 w-4 flex-shrink-0" />
              {label}
              {active && (
                <ChevronRight className="ml-auto h-3.5 w-3.5 opacity-50" />
              )}
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
  )
}
