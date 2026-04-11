// store/msgCenterStore.js
import { create } from "zustand";

export const useMsgCenterStore = create((set) => ({
  mode: "create",
  setMode: (m) => set({ mode: m }),

  // 🆕 new
  activeTab: null, // no global default
  setActiveTab: (tab) => set({ activeTab: tab }),
}));
