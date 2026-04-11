"use client";
import { create } from "zustand";
import { persist } from "zustand/middleware";

// ✅ create channel once
const balanceChannel =
  typeof window !== "undefined" && typeof window.BroadcastChannel === "function"
    ? (window.__balanceChannel ??= new window.BroadcastChannel("balance"))
    : null;

// ✅ unique id per tab (avoid self-update)
const TAB_ID = typeof window !== "undefined" ? Math.random().toString(36) : "";

const initialBalanceState = {
  credits: 0,
  points: 0,
  isTransferring: false,
  lastTransferDoneAt: 0,
};

export const useBalanceStore = create(
  persist(
    (set) => ({
      ...initialBalanceState,

      setCredits: (v) => {
        set({ credits: v });

        // 🔥 broadcast to other tabs
        balanceChannel?.postMessage({
          type: "UPDATE_CREDITS",
          credits: v,
          from: TAB_ID,
        });
      },

      setPoints: (v) => set({ points: v }),
      setIsTransferring: (v) => set({ isTransferring: v }),
      markTransferDone: () => set({ lastTransferDoneAt: Date.now() }),

      resetBalance: () => set(initialBalanceState),
    }),
    {
      name: "balance-store",
      partialize: (state) => ({
        credits: state.credits,
        lastTransferDoneAt: state.lastTransferDoneAt,
      }),
    },
  ),
);

// ✅ attach listener ONCE only
if (
  typeof window !== "undefined" &&
  balanceChannel &&
  !window.__balanceChannelAdded
) {
  window.__balanceChannelAdded = true;

  balanceChannel.onmessage = (event) => {
    const data = event.data;

    // ❗ ignore same tab
    if (data?.from === TAB_ID) return;

    if (data?.type === "UPDATE_CREDITS") {
      useBalanceStore.setState({
        credits: data.credits,
      });
    }
  };
}
