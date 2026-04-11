"use client";
import { createSlice } from "@reduxjs/toolkit";

const initialState = {
  memberId: null,
  otpCode: null,
  phone: "",
  dial: "+60", // default to Malaysia
};

const resetPwdSlice = createSlice({
  name: "resetPwd",
  initialState,
  reducers: {
    setResetPwdPayload: (state, action) => {
      state.memberId = action.payload.memberId ?? state.memberId;
      state.otpCode = action.payload.otpCode ?? state.otpCode;
      state.phone = action.payload.phone ?? state.phone;
      state.dial = action.payload.dial ?? state.dial;
    },
    clearResetPwd: () => initialState,
  },
});

export const { setResetPwdPayload, clearResetPwd } = resetPwdSlice.actions;
export default resetPwdSlice.reducer;
