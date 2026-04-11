// src/services/authApi.js
import { baseApi } from "./baseApi";

export const transactionApi = baseApi.injectEndpoints({
  endpoints: (builder) => ({
    paymentList: builder.query({
      query: (body) => ({
        url: "/api/member/payment/list",
        method: "POST",
        body,
      }),
    }),

    topup: builder.mutation({
      query: (body) => ({
        url: "/api/member/topup",
        method: "POST",
        body,
      }),
    }),

    topupStatus: builder.query({
      query: (body) => ({
        url: "/api/member/payment/status/deposit",
        method: "POST",
        body,
      }),
    }),

    topupStatusNow: builder.mutation({
      query: (body) => ({
        url: "/api/member/payment/status/deposit",
        method: "POST",
        body,
      }),
    }),

    withdraw: builder.mutation({
      query: (body) => ({
        url: "/api/member/withdraw",
        method: "POST",
        body,
      }),
    }),

    withdrawQr: builder.mutation({
      query: (body) => ({
        url: "/api/member/withdraw/qr",
        method: "POST",
        body,
      }),
    }),

    transactionList: builder.query({
      query: (body) => ({
        url: "/api/member/transaction/list",
        method: "POST",
        body,
      }),
      keepUnusedDataFor: Infinity, // 👈 关键
    }),

    fromCreditToPoint: builder.mutation({
      query: (body) => ({
        url: "/api/member/player/reload",
        method: "POST",
        body,
      }),
    }),

    fromPointToCredit: builder.mutation({
      query: (body) => ({
        url: "/api/member/player/withdraw",
        method: "POST",
        body,
      }),
    }),

    transferAllPointToCredit: builder.mutation({
      query: (body) => ({
        url: "/api/member/player/withdraw/out",
        method: "POST",
        body,
      }),
    }),

    transferList: builder.query({
      query: (body) => ({
        url: "/api/member/player/transfer/list",
        method: "POST",
        body,
      }),
    }),
    transferOut: builder.mutation({
      query: (body) => ({
        url: "/api/member/player/transfer/out",
        method: "POST",
        body,
      }),
    }),

    checkWithdrawStatus: builder.query({
      query: ({ user_id, credit_id }) => ({
        url: "/api/member/payment/status/withdraw",
        method: "POST",
        body: { user_id, credit_id },
      }),
    }),
    transferAllCreditToPoint: builder.mutation({
      query: ({ member_id, gamemember_id, ip }) => ({
        url: "/api/member/player/reload/out",
        method: "POST",
        body: {
          member_id,
          gamemember_id,
          ip,
        },
      }),
    }),

    // delete bank
  }),

  overrideExisting: false,
});

export const {
  usePaymentListQuery,
  useTopupMutation,
  useTopupStatusQuery,
  useTopupStatusNowMutation,
  useWithdrawMutation,
  useTransactionListQuery,
  useTransferListQuery,
  useFromCreditToPointMutation,
  useFromPointToCreditMutation,
  useTransferAllPointToCreditMutation,
  useTransferOutMutation,
  useCheckWithdrawStatusQuery,
  useWithdrawQrMutation,
  useTransferAllCreditToPointMutation,
} = transactionApi;
