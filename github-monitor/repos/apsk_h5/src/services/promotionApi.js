// src/services/authApi.js
import { baseApi } from "./baseApi";

export const promotionApi = baseApi.injectEndpoints({
  endpoints: (builder) => ({
    getPromotionList: builder.query({
      query: (body) => ({
        url: "/api/member/promotion/list",
        method: "POST",
        body,
      }),
    }),
  }),

  overrideExisting: false,
});

export const { useGetPromotionListQuery } = promotionApi;
