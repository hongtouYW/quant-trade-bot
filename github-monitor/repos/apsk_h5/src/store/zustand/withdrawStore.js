import { create } from "zustand";

export const useWithdrawFormStore = create((set) => ({
  // Only keep amount in Zustand
  amount: "",

  setAmount: (value) => set({ amount: value }),

  resetAmount: () => set({ amount: "" }),
  shouldReloadBanks: false,
  markShouldReloadBanks: () => set({ shouldReloadBanks: true }),
  clearShouldReloadBanks: () => set({ shouldReloadBanks: false }),
}));
