// src/services/authApi.js
import { baseApi } from "./baseApi";

export const referralApi = baseApi.injectEndpoints({
  endpoints: (builder) => ({
    getReferralTutorial: builder.query({
      query: (body) => ({
        url: "/api/member/referral/tutorial",
        method: "POST",
        body,
      }),
    }),
    getDownlineList: builder.query({
      query: (body) => ({
        url: "/api/member/performance/downline/list",
        method: "POST",
        body,
      }),
    }),
    getCommissionList: builder.query({
      query: (body) => ({
        url: "/api/member/performance/commission/list",
        method: "POST",
        body,
      }),
    }),

    getMyCommissionTotalList: builder.query({
      query: (body) => ({
        url: "/api/member/performance/commission/list/total",
        method: "POST",
        body,
      }),
    }),

    createNewInviteCode: builder.mutation({
      query: (body) => ({
        url: "/api/member/performance/invite/new",
        method: "POST",
        body,
      }),
    }),

    getInviteList: builder.query({
      query: (body) => ({
        url: "/api/member/performance/invite/list",
        method: "POST",
        body,
      }),
    }),

    editDefaultInviteCode: builder.mutation({
      query: (body) => ({
        url: "/api/member/performance/invite/default/edit",
        method: "POST",
        body,
      }),
    }),

    getPerformanceSummary: builder.query({
      query: (body) => ({
        url: "/api/member/performance/summary",
        method: "POST",
        body,
      }),
    }),

    getPerformanceProfile: builder.query({
      query: (body) => ({
        url: "/api/member/performance/profile",
        method: "POST",
        body: body,
      }),
    }),

    getReferralFriend: builder.query({
      query: (payload) => ({
        url: "/api/member/performance/friend/list",
        method: "POST",
        body: payload,
      }),
    }),

    getReferralFriendCommission: builder.query({
      query: (payload) => ({
        url: "/api/member/performance/friend/commission",
        method: "POST",
        body: payload,
      }),
    }),
    getReferralDirectUpline: builder.query({
      query: (payload) => ({
        url: "/api/member/performance/upline",
        method: "POST",
        body: payload,
      }),
    }),

    editInviteName: builder.mutation({
      query: (payload) => ({
        url: "/api/member/performance/invite/name/edit",
        method: "POST",
        body: payload,
      }),
    }),
  }),

  overrideExisting: false,
});

export const {
  useCreateNewInviteCodeMutation,
  useGetReferralTutorialQuery,
  useGetDownlineListQuery,
  useGetCommissionListQuery,
  useGetMyCommissionTotalListQuery,
  useGetInviteListQuery,
  useGetPerformanceSummaryQuery,
  useEditDefaultInviteCodeMutation,
  useGetPerformanceProfileQuery,
  useGetReferralFriendQuery,
  useGetReferralFriendCommissionQuery,
  useGetReferralDirectUplineQuery,
  useEditInviteNameMutation,
} = referralApi;
