import { createSlice } from "@reduxjs/toolkit";

const initialState = {
  selectedPromotion: null,
};

const promotionSlice = createSlice({
  name: "promotion",
  initialState,
  reducers: {
    setSelectedPromotion: (state, action) => {
      state.selectedPromotion = action.payload;
    },
    clearSelectedPromotion: () => initialState,
  },
});

export const { setSelectedPromotion, clearSelectedPromotion } =
  promotionSlice.actions;
export default promotionSlice.reducer;
