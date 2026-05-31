import { cn } from "@/lib/utils"
import { AlertTriangle, ShieldAlert, Info } from "lucide-react"

interface WarningChipsProps {
  fraudFlags?: string[]
  className?: string
}

type Severity = "high" | "medium" | "low"

interface FlagMeta {
  label: string
  severity: Severity
}

const FLAG_META: Record<string, FlagMeta> = {
  no_photos: { label: "No photos", severity: "medium" },
  price_outlier: { label: "Price outlier", severity: "high" },
  broker_unverified: { label: "Unverified broker", severity: "medium" },
  title_irregular: { label: "Irregular title", severity: "high" },
  duplicate_listing: { label: "Possible duplicate", severity: "low" },
  developer_flagged: { label: "Developer complaints", severity: "high" },
  copy_paste_description: { label: "Copied description", severity: "low" },
  photo_reused: { label: "Reused photos", severity: "medium" },
  contact_mismatch: { label: "Contact mismatch", severity: "high" },
  price_too_low: { label: "Price too low", severity: "high" },
}

function severityClasses(s: Severity) {
  if (s === "high") return "border-red-200 bg-red-50 text-red-700"
  if (s === "medium") return "border-amber-200 bg-amber-50 text-amber-700"
  return "border-gray-200 bg-gray-50 text-gray-600"
}

function SeverityIcon({ s }: { s: Severity }) {
  const cls = "h-3 w-3 flex-shrink-0"
  if (s === "high") return <ShieldAlert className={cls} />
  if (s === "medium") return <AlertTriangle className={cls} />
  return <Info className={cls} />
}

export function WarningChips({ fraudFlags, className }: WarningChipsProps) {
  if (!fraudFlags?.length) return null

  return (
    <div className={cn("flex flex-wrap gap-1.5", className)}>
      {fraudFlags.map((flag) => {
        const meta = FLAG_META[flag] ?? { label: flag, severity: "low" as Severity }
        return (
          <span
            key={flag}
            className={cn(
              "flex items-center gap-1 rounded-full border px-2 py-0.5 text-[11px] font-medium",
              severityClasses(meta.severity),
            )}
          >
            <SeverityIcon s={meta.severity} />
            {meta.label}
          </span>
        )
      })}
    </div>
  )
}
