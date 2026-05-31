import { type ClassValue, clsx } from "clsx"
import { twMerge } from "tailwind-merge"

export function cn(...inputs: ClassValue[]) {
  return twMerge(clsx(inputs))
}

export function formatPHP(amount: number, maximumFractionDigits = 0) {
  return new Intl.NumberFormat("en-PH", {
    style: "currency",
    currency: "PHP",
    maximumFractionDigits,
  }).format(amount)
}

export function formatNumber(n: number) {
  return new Intl.NumberFormat("en-PH").format(n)
}

export function dtiClass(dti: number): string {
  if (dti <= 0.3) return "dti-safe"
  if (dti <= 0.35) return "dti-caution"
  if (dti <= 0.4) return "dti-warning"
  return "dti-danger"
}

export function scoreColor(score: number): string {
  if (score >= 80) return "text-score-excellent"
  if (score >= 60) return "text-score-good"
  if (score >= 40) return "text-score-fair"
  return "text-score-poor"
}

export function truncate(str: string, maxLen: number) {
  return str.length <= maxLen ? str : str.slice(0, maxLen - 1) + "…"
}
