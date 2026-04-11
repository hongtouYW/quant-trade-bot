import { createSlice } from "@reduxjs/toolkit";

const emptyState = {
  rows: [],
  page: 1,
  hasNextPage: true,
};

const initialState = {
  credit: { ...emptyState },
  point: { ...emptyState },
  history: { ...emptyState },
};

const transactionFilterSlice = createSlice({
  name: "transactionFilter",
  initialState,
  reducers: {
    resetList(state, action) {
      const type = action.payload;
      if (!type) return;
      state[type] = { ...emptyState };
    },

    nextPage(state, action) {
      const type = action.payload;
      if (!type) return;

      state[type].page += 1;
    },

    appendPage(state, action) {
      const { type, rows, page, hasNextPage } = action.payload;
      if (!type) return;

      const target = state[type];

      // If we are returning to Page 1 and already have data,
      // only update if the new data is actually different
      // (prevents UI flicker on back navigation)
      if (page === 1) {
        target.rows = rows;
      } else {
        const seen = new Set(target.rows.map((r) => r.id));
        const newRows = rows.filter((r) => !seen.has(r.id));
        target.rows.push(...newRows);
      }

      target.page = page;
      target.hasNextPage = hasNextPage;
    },
  },
});

export const { resetList, appendPage, nextPage } =
  transactionFilterSlice.actions;

export default transactionFilterSlice.reducer;
