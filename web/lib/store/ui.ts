import { create } from "zustand"

interface UIState {
  sidebarOpen: boolean
  advisorOpen: boolean
  toggleSidebar: () => void
  setSidebarOpen: (open: boolean) => void
  toggleAdvisor: () => void
  setAdvisorOpen: (open: boolean) => void
}

export const useUIStore = create<UIState>()((set) => ({
  sidebarOpen: false,
  advisorOpen: false,

  toggleSidebar: () => set((s) => ({ sidebarOpen: !s.sidebarOpen })),
  setSidebarOpen: (open) => set({ sidebarOpen: open }),

  toggleAdvisor: () => set((s) => ({ advisorOpen: !s.advisorOpen })),
  setAdvisorOpen: (open) => set({ advisorOpen: open }),
}))
