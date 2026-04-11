// src/services/authApi.js

import { getCookie } from "@/utils/cookie";
import { baseApi } from "./baseApi";

export const feedbackApi = baseApi.injectEndpoints({
  endpoints: (builder) => ({
    sendFeedBack: builder.mutation({
      query: (formData) => ({
        url: "/api/member/feedback/send",
        method: "POST",
        body: formData,
      }),
    }),

    getMemberFeedbackList: builder.query({
      query: (user_id) => ({
        url: "/api/member/feedback/list",
        method: "POST",
        body: { user_id },
      }),
    }),
  }),

  overrideExisting: false,
});

export const { useSendFeedBackMutation, useGetMemberFeedbackListQuery } =
  feedbackApi;
