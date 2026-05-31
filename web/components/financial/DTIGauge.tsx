"use client"

import { cn, dtiClass } from "@/lib/utils"

interface DTIGaugeProps {
  ratio: number
  label?: string
  className?: string
}

export function DTIGauge({ ratio, label, className }: DTIGaugeProps) {
  const pct = Math.min(100, Math.round(ratio * 100))
  const clsName = dtiClass(ratio)

  const statusLabel =
    ratio <= 0.3
      ? "Within healthy range"
      : ratio <= 0.35
        ? "Slightly elevated"
        : ratio <= 0.4
          ? "High — proceed with caution"
          : "Exceeds safe limit"

  const barColor =
    ratio <= 0.3
      ? "bg-green-500"
      : ratio <= 0.35
        ? "bg-amber-400"
        : ratio <= 0.4
          ? "bg-orange-500"
          : "bg-red-500"

  return (
    <div className={cn("space-y-1.5", className)}>
      <div className="flex items-center justify-between">
        <span className="text-xs text-gray-500">{label ?? "Debt-to-Income"}</span>
        <span className={cn("text-sm font-semibold tabular-nums", clsName)}>
          {pct}%
        </span>
      </div>
      {/* Track */}
      <div className="relative h-2 overflow-hidden rounded-full bg-gray-100">
        {/* 30 % marker */}
        <div
          className="absolute top-0 h-full w-px bg-gray-300"
          style={{ left: "30%" }}
        />
        {/* 35 % marker */}
        <div
          className="absolute top-0 h-full w-px bg-gray-300"
          style={{ left: "35%" }}
        />
        {/* Fill */}
        <div
          className={cn("h-full rounded-full transition-all duration-500", barColor)}
          style={{ width: `${pct}%` }}
        />
      </div>
      <p className={cn("text-[10px] font-medium", clsName)}>{statusLabel}</p>
    </div>
  )
}
