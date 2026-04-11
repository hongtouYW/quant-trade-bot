// store/otpResetSlice.js
import { createSlice } from "@reduxjs/toolkit";

const initialState = {
  phone: "",
  dial: "",
  otpcode: "",
  password: "",
  verifyby: "",
  module: "",
  // add anything else you need, e.g. flowId, token, etc.
};

const otpVerifySlice = createSlice({
  name: "otpVerify",
  initialState,
  reducers: {
    setOtpPayload: (state, action) => {
      const { phone, dial, otpcode, password, verifyby, module } =
        action.payload || {};
      state.phone = phone || "";
      state.dial = dial || "";
      state.otpcode = otpcode || "";
      state.password = password || "";

      state.verifyby = verifyby || "";
      state.module = module || "";
    },
    clearOtpPayload: () => initialState,
  },
});

export const { setOtpPayload, clearOtpPayload } = otpVerifySlice.actions;
export default otpVerifySlice.reducer;
