import { createSlice } from "@reduxjs/toolkit";

const initialState = {
  currentTransaction: null,
};

const transactionDetailSlice = createSlice({
  name: "transactionDetail",
  initialState,
  reducers: {
    setCurrentTransaction: (state, action) => {
      state.currentTransaction = action.payload;
    },
    clearCurrentTransaction: (state) => {
      state.currentTransaction = null;
    },
  },
});

export const { setCurrentTransaction, clearCurrentTransaction } =
  transactionDetailSlice.actions;

export default transactionDetailSlice.reducer;
