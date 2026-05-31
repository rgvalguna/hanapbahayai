import { cn, scoreColor } from "@/lib/utils"
import { TrendingUp } from "lucide-react"

interface ScoreBadgeProps {
  total: number
  dimensions?: Record<string, number>
  size?: "sm" | "md"
}

const DIMENSION_LABELS: Record<string, string> = {
  affordability: "Affordability",
  commute: "Commute",
  safety: "Safety",
  flood: "Flood risk",
  education: "Education",
  healthcare: "Healthcare",
  internet: "Internet",
  investment: "Investment",
  developer: "Developer",
  livability: "Livability",
}

export function ScoreBadge({ total, dimensions, size = "md" }: ScoreBadgeProps) {
  const rounded = Math.round(total)

  return (
    <div className="group relative">
      <div
        className={cn(
          "flex items-center gap-1.5 rounded-full border px-3 py-1 font-semibold tabular-nums",
          size === "sm" ? "text-xs" : "text-sm",
          rounded >= 80
            ? "border-green-200 bg-green-50 text-green-700"
            : rounded >= 60
              ? "border-blue-200 bg-blue-50 text-blue-700"
              : rounded >= 40
                ? "border-amber-200 bg-amber-50 text-amber-700"
                : "border-red-200 bg-red-50 text-red-700",
        )}
      >
        <TrendingUp className={cn("flex-shrink-0", size === "sm" ? "h-3 w-3" : "h-3.5 w-3.5")} />
        <span>{rounded}/100</span>
      </div>

      {dimensions && Object.keys(dimensions).length > 0 && (
        <div className="absolute left-0 top-full z-20 mt-2 hidden w-56 rounded-xl border border-[--color-border] bg-white p-3 shadow-[--shadow-elevated] group-hover:block">
          <p className="mb-2 text-[10px] font-semibold uppercase tracking-wide text-gray-400">
            Score breakdown
          </p>
          <div className="space-y-1.5">
            {Object.entries(dimensions).map(([key, val]) => (
              <div key={key} className="flex items-center gap-2">
                <span className="w-24 truncate text-xs text-gray-600">
                  {DIMENSION_LABELS[key] ?? key}
                </span>
                <div className="flex-1 overflow-hidden rounded-full bg-gray-100">
                  <div
                    className={cn(
                      "h-1.5 rounded-full transition-all",
                      val >= 80
                        ? "bg-green-500"
                        : val >= 60
                          ? "bg-blue-500"
                          : val >= 40
                            ? "bg-amber-400"
                            : "bg-red-400",
                    )}
                    style={{ width: `${Math.min(100, Math.round(val))}%` }}
                  />
                </div>
                <span className={cn("w-7 text-right text-xs tabular-nums font-medium", scoreColor(val))}>
                  {Math.round(val)}
                </span>
              </div>
            ))}
          </div>
        </div>
      )}
    </div>
  )
}
