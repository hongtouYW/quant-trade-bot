"use client";

import { createSlice } from "@reduxjs/toolkit";

const initialState = {
  current: null, // { ...the item user clicked }
};

const bankDetailSlice = createSlice({
  name: "bankDetail",
  initialState,
  reducers: {
    setCurrentBank(state, action) {
      state.current = action.payload || null;
    },
    clearCurrentBank(state) {
      state.current = null;
    },
  },
});

export const { setCurrentBank, clearCurrentBank } = bankDetailSlice.actions;
export default bankDetailSlice.reducer;
