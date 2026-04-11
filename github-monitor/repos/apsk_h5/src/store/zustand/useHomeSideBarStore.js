import { create } from "zustand";
export const useHomeSideBarStore = create((set) => ({
  // 🏠 Sidebar states
  activeId: "all", // default
  setActiveId: (tab) => set({ activeId: tab }),

  activeTab: "hot",
  setActiveTab: (tab) => set({ activeTab: tab }),

  activePid: null,
  setActivePid: (pid) => set({ activePid: pid }),
  // 🔁 Reset everything
  reset: () =>
    set({
      activeId: "all",
      activeTab: "hot",
      activePid: null,
    }),
}));
