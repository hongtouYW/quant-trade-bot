// src/services/authApi.js
import { baseApi } from "./baseApi";

export const vipApi = baseApi.injectEndpoints({
  endpoints: (builder) => ({
    // 📌 VIP List
    vipList: builder.query({
      query: (body) => ({
        url: "/api/member/vip/list",
        method: "POST",
        body,
      }),
    }),

    // 📌 VIP First Bonus
    claimFirstBonus: builder.mutation({
      query: ({ member_id, vip_id = null }) => ({
        url: "/api/member/vip/bonus/first",
        method: "POST",
        body: {
          member_id,
          vip_id,
        },
      }),
    }),

    // 📌 VIP Weekly Bonus
    claimWeeklyBonus: builder.mutation({
      query: (member_id) => ({
        url: "/api/member/vip/bonus/weekly",
        method: "POST",
        body: { member_id },
      }),
    }),

    // 📌 VIP Monthly Bonus
    claimMonthlyBonus: builder.mutation({
      query: (member_id) => ({
        url: "/api/member/vip/bonus/monthly",
        method: "POST",
        body: { member_id },
      }),
    }),

    // 📌 VIP Remain Target
    getVipRemainTarget: builder.query({
      query: (member_id) => ({
        url: "/api/member/vip/bonus/remain/target",
        method: "POST",
        body: { member_id },
      }),
    }),

    getVipBonusHistory: builder.query({
      query: ({ member_id, startdate = null, enddate = null }) => ({
        url: "/api/member/vip/bonus/history",
        method: "POST",
        body: {
          member_id,
          startdate,
          enddate,
        },
      }),
    }),
    claimnVipBonusAll: builder.mutation({
      query: (member_id) => ({
        url: "/api/member/vip/bonus/all",
        method: "POST",
        body: { member_id },
      }),
    }),
  }),

  overrideExisting: false,
});

export const {
  useVipListQuery,
  useClaimFirstBonusMutation,
  useClaimWeeklyBonusMutation,
  useClaimMonthlyBonusMutation,
  useGetVipRemainTargetQuery,
  useGetVipBonusHistoryQuery,
  useClaimnVipBonusAllMutation,
} = vipApi;
