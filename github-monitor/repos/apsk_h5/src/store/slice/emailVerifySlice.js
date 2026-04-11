// store/otpResetSlice.js
import { createSlice } from "@reduxjs/toolkit";

const initialState = {
  otpcode: "",
  memberId: "",
  email: "",
};

const emailVerifySlice = createSlice({
  name: "emailVerify",
  initialState,
  reducers: {
    setEmailPayload: (state, action) => {
      const { otpcode, memberId, email } = action.payload || {};
      state.otpcode = otpcode || "";
      state.memberId = memberId || "";
      state.email = email || "";
    },
    clearEmailPayload: () => initialState,
  },
});

export const { setEmailPayload, clearEmailPayload } = emailVerifySlice.actions;
export default emailVerifySlice.reducer;
