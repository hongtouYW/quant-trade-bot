// src/services/authApi.js
import { baseApi } from "./baseApi";

export const memberBankApi = baseApi.injectEndpoints({
  endpoints: (builder) => ({
    memberBank: builder.query({
      query: (body) => ({
        url: "/api/member/bank/list",
        method: "POST",
        body,
      }),
    }),
    allBank: builder.query({
      query: (body) => ({
        url: "/api/bank/list",
        method: "POST",
        body,
      }),
    }),

    addBank: builder.mutation({
      query: (body) => ({
        url: "/api/member/bank/add",
        method: "POST",
        body,
      }),
    }),

    // toggle/update fastpay
    updateBankFastpay: builder.mutation({
      query: (body) => ({
        url: "/api/member/bank/fastpay",
        method: "POST",
        body,
      }),
    }),

    // delete bank
    deleteBank: builder.mutation({
      query: ({ bankaccount_id, member_id }) => ({
        url: "/api/member/bank/delete",
        method: "POST",
        body: { bankaccount_id, member_id },
      }),
    }),
  }),

  overrideExisting: false,
});

export const {
  useMemberBankQuery,
  useLazyMemberBankQuery,
  useAllBankQuery,
  useAddBankMutation,
  useDeleteBankMutation,
  useUpdateBankFastpayMutation,
} = memberBankApi;
