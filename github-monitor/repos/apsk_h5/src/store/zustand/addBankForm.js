"use client";

import { create } from "zustand";
import { persist } from "zustand/middleware";

export const useAddBankFormStore = create()(
  persist(
    (set, get) => ({
      // form fields
      name: "",
      account: "",
      fastPay: true,

      // selected bank
      selectedBankId: null,
      selectedBankName: "",

      // navigation flag: true when coming back from picker
      fromPicker: false,

      // setters
      setName: (v) => set({ name: v }),
      setAccount: (v) => set({ account: v }),
      setFastPay: (v) => set({ fastPay: v }),

      setSelectedBank: (id, name) =>
        set({
          selectedBankId: id ?? null,
          selectedBankName: name ?? "",
        }),

      markFromPicker: (v) => set({ fromPicker: !!v }),

      // clear everything
      reset: () =>
        set({
          name: "",
          account: "",
          fastPay: true,
          selectedBankId: null,
          selectedBankName: "",
          fromPicker: false,
        }),
    }),
    {
      name: "addBankForm", // session key
      getStorage: () => sessionStorage, // survive route changes, but tab-scoped
    }
  )
);
