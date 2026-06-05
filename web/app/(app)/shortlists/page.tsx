"use client"

import { useState } from "react"
import { useQuery, useMutation, useQueryClient } from "@tanstack/react-query"
import type { Metadata } from "next"
import {
  getShortlists,
  createShortlist,
  type Shortlist,
} from "@/lib/api/listings"
import { Heart, Trash2, Plus, Loader2 } from "lucide-react"

export default function ShortlistsPage() {
  const qc = useQueryClient()
  const [newName, setNewName] = useState("")
  const [creating, setCreating] = useState(false)

  const { data: shortlists, isLoading, error } = useQuery({
    queryKey: ["shortlists"],
    queryFn: getShortlists,
  })

  const createMutation = useMutation({
    mutationFn: (name: string) => createShortlist(name),
    onSuccess: () => {
      void qc.invalidateQueries({ queryKey: ["shortlists"] })
      setNewName("")
      setCreating(false)
    },
  })

  if (isLoading) {
    return (
      <div className="flex items-center justify-center py-24">
        <Loader2 className="h-6 w-6 animate-spin text-[--color-brand-500]" />
      </div>
    )
  }

  if (error) {
    return (
      <div className="rounded-xl border border-red-200 bg-red-50 p-6 text-sm text-red-700">
        Failed to load shortlists. Please refresh the page.
      </div>
    )
  }

  const lists = shortlists ?? []

  return (
    <div>
      <div className="mb-6 flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-semibold text-gray-900">Your shortlists</h1>
          <p className="mt-1 text-sm text-gray-500">
            Saved properties grouped by criteria or decision stage.
          </p>
        </div>
        <button
          onClick={() => setCreating(true)}
          className="flex items-center gap-1.5 rounded-lg bg-[--color-brand-600] px-4 py-2 text-sm font-semibold text-white transition hover:bg-[--color-brand-700]"
        >
          <Plus className="h-4 w-4" />
          New shortlist
        </button>
      </div>

      {/* Create form */}
      {creating && (
        <div className="mb-4 flex gap-2">
          <input
            autoFocus
            type="text"
            placeholder="Shortlist name…"
            value={newName}
            onChange={(e) => setNewName(e.target.value)}
            onKeyDown={(e) => {
              if (e.key === "Enter" && newName.trim()) createMutation.mutate(newName.trim())
              if (e.key === "Escape") { setCreating(false); setNewName("") }
            }}
            className="flex-1 rounded-lg border border-[--color-border] px-3 py-2 text-sm focus:border-[--color-brand-500] focus:outline-none focus:ring-2 focus:ring-[--color-brand-500]/20"
          />
          <button
            onClick={() => newName.trim() && createMutation.mutate(newName.trim())}
            disabled={!newName.trim() || createMutation.isPending}
            className="rounded-lg bg-[--color-brand-600] px-4 py-2 text-sm font-semibold text-white disabled:opacity-50"
          >
            {createMutation.isPending ? <Loader2 className="h-4 w-4 animate-spin" /> : "Create"}
          </button>
          <button
            onClick={() => { setCreating(false); setNewName("") }}
            className="rounded-lg border border-[--color-border] px-4 py-2 text-sm text-gray-500 hover:bg-gray-50"
          >
            Cancel
          </button>
        </div>
      )}

      {lists.length === 0 ? (
        <div className="flex flex-col items-center justify-center rounded-2xl border border-dashed border-[--color-border] bg-white py-24 text-center">
          <div className="mb-3 flex h-12 w-12 items-center justify-center rounded-full bg-[--color-brand-50] text-[--color-brand-400]">
            <Heart className="h-6 w-6" />
          </div>
          <p className="mb-1 text-sm font-medium text-gray-700">No saved properties yet</p>
          <p className="mb-4 max-w-xs text-xs text-gray-400">
            Browse properties and save the ones you want to compare later.
          </p>
          <a
            href="/listings"
            className="rounded-lg border border-[--color-brand-300] px-4 py-2 text-sm font-medium text-[--color-brand-700] transition hover:bg-[--color-brand-50]"
          >
            Browse properties
          </a>
        </div>
      ) : (
        <div className="space-y-4">
          {lists.map((list) => (
            <ShortlistCard key={list.id} shortlist={list} />
          ))}
        </div>
      )}
    </div>
  )
}

function ShortlistCard({ shortlist }: { shortlist: Shortlist }) {
  const qc = useQueryClient()
  const count = shortlist.listing_ids?.length ?? 0

  return (
    <div className="rounded-2xl border border-[--color-border] bg-white p-5">
      <div className="flex items-start justify-between">
        <div>
          <h2 className="font-semibold text-gray-900">{shortlist.name}</h2>
          <p className="mt-0.5 text-xs text-gray-500">
            {count} {count === 1 ? "property" : "properties"}
          </p>
        </div>
      </div>
      {count === 0 && (
        <p className="mt-4 rounded-lg border border-dashed border-[--color-border] py-6 text-center text-xs text-gray-400">
          No properties saved yet.{" "}
          <a href="/listings" className="text-[--color-brand-600] hover:underline">
            Browse listings
          </a>
        </p>
      )}
    </div>
  )
}

