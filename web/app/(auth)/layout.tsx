import type { Metadata } from "next"
import Link from "next/link"
import { MapPin } from "lucide-react"

export const metadata: Metadata = {
  title: {
    template: "%s | HanapBahay AI",
    default: "Sign in | HanapBahay AI",
  },
}

export default function AuthLayout({ children }: { children: React.ReactNode }) {
  return (
    <div className="flex min-h-screen flex-col bg-[--color-surface]">
      <div className="flex flex-1 flex-col items-center justify-center px-4 py-12">
        <Link href="/" className="mb-8 flex items-center gap-2">
          <span className="text-2xl font-bold text-[--color-brand-600]">HanapBahay</span>
          <span className="rounded-full bg-[--color-brand-100] px-2 py-0.5 text-xs font-semibold text-[--color-brand-700]">
            AI
          </span>
        </Link>
        <div className="w-full max-w-md">{children}</div>
      </div>
      <footer className="py-6 text-center text-sm text-gray-400">
        <MapPin className="mr-1 inline h-3.5 w-3.5" />
        Built for Filipino home buyers · Independent of all developers & brokers
      </footer>
    </div>
  )
}
