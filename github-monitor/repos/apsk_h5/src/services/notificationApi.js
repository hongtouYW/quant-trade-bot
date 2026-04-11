// src/services/authApi.js

import { getCookie } from "@/utils/cookie";
import { baseApi } from "./baseApi";

export const notificationApi = baseApi.injectEndpoints({
  endpoints: (builder) => ({
    getNotificationList: builder.query({
      query: (body) => ({
        url: "/api/member/notification/list",
        method: "POST",
        body,
      }),
    }),

    markNotificationRead: builder.mutation({
      query: (body) => ({
        url: "/api/member/notification/read",
        method: "POST",
        body,
      }),
    }),
  }),

  overrideExisting: true,
});

export const { useGetNotificationListQuery, useMarkNotificationReadMutation } =
  notificationApi;
