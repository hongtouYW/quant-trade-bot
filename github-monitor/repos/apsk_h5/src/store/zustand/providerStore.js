"use client";
import { create } from "zustand";
import { persist, createJSONStorage } from "zustand/middleware";

export const useProviderStore = create(
  persist(
    (set, get) => ({
      // 🧩 per-tab data (sessionStorage)
      selectedProvider: null,
      setSelectedProvider: (provider) => set({ selectedProvider: provider }),
      reset: () => set({ selectedProvider: null }),

      // 🧩 shared cross-tab data (localStorage)
      prevGameMemberId:
        typeof window !== "undefined"
          ? localStorage.getItem("prevGameMemberId") || null
          : null,

      prevProviderId:
        typeof window !== "undefined"
          ? localStorage.getItem("prevProviderId") || null
          : null,

      // 🟨 setters for prevProviderId
      setPrevProviderId: (id) => {
        set({ prevProviderId: id });
        if (typeof window !== "undefined") {
          if (id == null) {
            localStorage.removeItem("prevProviderId");
          } else {
            localStorage.setItem("prevProviderId", id);
          }
        }
      },

      setPrevGameMemberId: (id) => {
        // ✅ update Zustand state
        set({ prevGameMemberId: id });

        // ✅ also update real localStorage for cross-tab sync
        if (typeof window !== "undefined") {
          if (id === null || id === undefined) {
            localStorage.removeItem("prevGameMemberId");
          } else {
            localStorage.setItem("prevGameMemberId", id);
          }
        }
      },

      clearPrevGameMemberId: () => {
        set({ prevGameMemberId: null });
        if (typeof window !== "undefined") {
          localStorage.removeItem("prevGameMemberId");
        }
      },

      clearPrevProviderId: () => {
        set({ prevProviderId: null });
        if (typeof window !== "undefined") {
          localStorage.removeItem("prevProviderId");
        }
      },
    }),
    {
      name: "provider-store",
      storage: createJSONStorage(() => sessionStorage), // ✅ provider state per-tab
      partialize: (state) => ({
        // ✅ only persist per-tab data (not localStorage one)
        selectedProvider: state.selectedProvider,
      }),
    }
  )
);

// 🟦 Sync cross-tab localStorage to Zustand
if (typeof window !== "undefined") {
  window.addEventListener("storage", (event) => {
    if (event.key === "prevGameMemberId") {
      useProviderStore.setState({ prevGameMemberId: event.newValue });
    }
    if (event.key === "prevProviderId") {
      useProviderStore.setState({ prevProviderId: event.newValue });
    }
  });
}
