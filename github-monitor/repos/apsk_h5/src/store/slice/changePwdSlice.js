"use client";
import { createSlice } from "@reduxjs/toolkit";

const defaultState = {
  memberId: null,
  bindphone: 0,
  bindemail: 0,
  bindgoogle: 0,
  phone: null,
  newPw: null,
  email: null,
};

// ✅ Load from localStorage
const getInitialState = () => {
  if (typeof window === "undefined") return defaultState;

  try {
    const saved = localStorage.getItem("changePwd");
    return saved ? JSON.parse(saved) : defaultState;
  } catch (err) {
    console.error("Failed to parse changePwd storage:", err);
    return defaultState;
  }
};

const initialState = getInitialState();

const changePwdSlice = createSlice({
  name: "changePwd",
  initialState,
  reducers: {
    setChangePwdPayload: (state, action) => {
      state.memberId = action.payload.memberId ?? state.memberId;
      state.bindphone = action.payload.bindphone ?? state.bindphone;
      state.bindemail = action.payload.bindemail ?? state.bindemail;
      state.bindgoogle = action.payload.bindgoogle ?? state.bindgoogle;
      state.phone = action.payload.phone ?? state.phone;
      state.newPw = action.payload.newPw ?? state.newPw;
      state.email = action.payload.email ?? state.email;

      // ✅ Save to localStorage
      if (typeof window !== "undefined") {
        localStorage.setItem("changePwd", JSON.stringify(state));
      }
    },

    clearChangePwd: () => {
      if (typeof window !== "undefined") {
        localStorage.removeItem("changePwd");
      }
      return defaultState;
    },
  },
});

export const { setChangePwdPayload, clearChangePwd } = changePwdSlice.actions;

export default changePwdSlice.reducer;
