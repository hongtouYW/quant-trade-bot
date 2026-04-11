// src/services/authApi.js
import { baseApi } from "./baseApi";

export const gameApi = baseApi.injectEndpoints({
  endpoints: (builder) => ({
    getProviderList: builder.query({
      query: (body) => ({
        url: "/api/member/game/provider/list",
        method: "POST",
        body,
      }),
    }),

    getProviderView: builder.query({
      query: (body) => ({
        url: "/api/member/game/provider/view",
        method: "POST",
        body,
      }),
    }),

    getGameList: builder.query({
      query: (body) => ({
        url: "/api/member/game/list",
        method: "POST",
        body,
      }),
    }),

    getPlatormList: builder.query({
      query: (body) => ({
        url: "/api/member/game/platform/list",
        method: "POST",
        body,
      }),
    }),

    getGameView: builder.query({
      query: (body) => ({
        url: "/api/member/game/view",
        method: "POST",
        body,
      }),
    }),

    openGameUrl: builder.mutation({
      query: (body) => ({
        url: "/api/member/player/v3/login",
        // url: "/api/member/player/login",
        method: "POST",
        body,
      }),
    }),

    getRecommendedGames: builder.query({
      query: (limit = 10) => ({
        url: `/api/games/recommends`,
        method: "GET",
        params: { limit },
      }),
    }),
  }),

  overrideExisting: false,
});

export const {
  useGetProviderViewQuery,
  useGetProviderListQuery,
  useGetPlatormListQuery,
  useGetGameListQuery,
  useGetGameViewQuery,
  useOpenGameUrlMutation,
} = gameApi;
