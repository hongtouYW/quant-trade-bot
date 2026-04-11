import { create } from "zustand";
import { persist, createJSONStorage } from "zustand/middleware";

export const useGameStore = create(
  persist(
    (set) => ({
      selectedGameId: null,
      setSelectedGameId: (id) => set({ selectedGameId: id }),
      reset: () => set({ selectedGameId: null }),
    }),
    {
      name: "game-store",
      storage: createJSONStorage(() => sessionStorage), // ✅ per-tab persistence
    }
  )
);
