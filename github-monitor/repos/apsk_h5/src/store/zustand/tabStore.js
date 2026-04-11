import { create } from "zustand";

export const useTabStore = create((set) => ({
  activeTab: "notification", // default
  setActiveTab: (tab) => set({ activeTab: tab }),
}));
