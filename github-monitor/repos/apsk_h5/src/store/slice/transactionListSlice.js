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

const transactionListSlice = createSlice({
  name: "transactionList",
  initialState,
  reducers: {
    resetList(state, action) {
      const type = action.payload; // credit | point | history
      state[type] = { ...emptyState };
    },

    nextPage(state, action) {
      const type = action.payload; // credit | point | history
      state[type].page += 1;
    },

    appendPage(state, action) {
      const { type, rows, page, hasNextPage } = action.payload;

      // 🔥 IMPORTANT GUARD
      if (
        page !== 1 &&
        state[type].page === 1 &&
        state[type].rows.length === 0
      ) {
        return;
      }

      const getKey = (item) => {
        if (type === "credit") return item.credit_id;
        if (type === "point") return item.gamepoint_id;
        if (type === "history") return item.gamelog_id;
        return item.id;
      };

      if (page === 1) {
        state[type].rows = rows;
      } else {
        const seen = new Set(state[type].rows.map(getKey));
        state[type].rows.push(...rows.filter((r) => !seen.has(getKey(r))));
      }

      state[type].page = page;
      state[type].hasNextPage = hasNextPage;
    },
  },
});

export const { resetList, appendPage, nextPage } = transactionListSlice.actions;
export default transactionListSlice.reducer;
