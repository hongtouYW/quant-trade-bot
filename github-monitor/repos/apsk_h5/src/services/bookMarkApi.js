// src/services/authApi.js
import { baseApi } from "./baseApi";

export const addBorkMarkApi = baseApi.injectEndpoints({
  endpoints: (builder) => ({
    addBookmark: builder.mutation({
      query: (body) => ({
        url: "/api/member/game/bookmark/add",
        method: "POST",
        body,
      }),
    }),

    bookmarkList: builder.query({
      query: (body) => ({
        url: "/api/member/game/bookmark/list",
        method: "POST",
        body,
      }),
    }),

    deleteBookmark: builder.mutation({
      query: (body) => ({
        url: "/api/member/game/bookmark/delete",
        method: "POST",
        body,
      }),
    }),
  }),

  overrideExisting: false,
});

export const {
  useAddBookmarkMutation,
  useBookmarkListQuery,
  useDeleteBookmarkMutation,
} = addBorkMarkApi;
