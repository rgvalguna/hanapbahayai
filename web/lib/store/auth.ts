import { create } from "zustand"
import { persist } from "zustand/middleware"

export interface AuthUser {
  id: string
  name: string
  email: string
  avatar_url?: string | null
  has_profile: boolean
  roles: string[]
}

interface AuthState {
  user: AuthUser | null
  setUser: (user: AuthUser) => void
  clearUser: () => void
  isAuthenticated: () => boolean
  hasRole: (role: string) => boolean
}

export const useAuthStore = create<AuthState>()(
  persist(
    (set, get) => ({
      user: null,

      setUser: (user) => set({ user }),

      clearUser: () => set({ user: null }),

      isAuthenticated: () => get().user !== null,

      hasRole: (role) => get().user?.roles.includes(role) ?? false,
    }),
    {
      name: "hanapbahay-auth",
      partialize: (state) => ({ user: state.user }),
    },
  ),
)
