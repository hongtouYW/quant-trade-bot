// store/helpSlice.js
import { createSlice } from "@reduxjs/toolkit";

const initialState = {
  selectedQuestion: null,
};

const helpSlice = createSlice({
  name: "help",
  initialState,
  reducers: {
    setSelectedQuestion: (state, action) => {
      state.selectedQuestion = action.payload || null;
    },
    clearSelectedQuestion: () => initialState,
  },
});

export const { setSelectedQuestion, clearSelectedQuestion } = helpSlice.actions;
export default helpSlice.reducer;
