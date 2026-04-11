// src/services/authApi.js
import { baseApi } from "./baseApi";

export const authApi = baseApi.injectEndpoints({
  endpoints: (builder) => ({
    login: builder.mutation({
      query: (body) => ({
        url: "/api/member/login",
        method: "POST",
        body,
      }),
    }),

    register: builder.mutation({
      query: (body) => ({
        url: "/api/member/register",
        method: "POST",
        body,
      }),
    }),

    updateProfile: builder.mutation({
      query: (body) => ({
        url: "/api/member/edit",
        method: "POST",
        body,
      }),
    }),

    getPlayerList: builder.query({
      query: (body) => ({
        url: "/api/member/player/list",
        method: "POST",
        body,
      }),
    }),

    getPlayerView: builder.query({
      query: (body) => ({
        url: "/api/member/player/view",
        method: "POST",
        body,
      }),
    }),

    getMemberView: builder.query({
      query: (body) => ({
        url: "/api/member/view",
        method: "POST",
        body,
      }),
    }),

    getPlayerPassword: builder.query({
      query: (body) => ({
        url: "/api/member/player/password",
        method: "POST",
        body,
      }),
    }),

    addPlayer: builder.mutation({
      query: (body) => ({
        url: "/api/member/player/add",
        method: "POST",
        body,
      }),
    }),

    bindEmail: builder.mutation({
      query: (body) => ({
        url: "/api/member/bind/email",
        method: "POST",
        body,
      }),
    }),

    generateGoogle2faQr: builder.query({
      query: (body) => ({
        url: "/api/member/google/generate/2fa/qr",
        method: "POST",
        body,
      }),
    }),

    verifyGoogle2fa: builder.mutation({
      query: (body) => ({
        url: "/api/member/bind/google", // ← change if your actual path differs
        method: "POST",
        body,
      }),
    }),

    verifyPhoneForReset: builder.mutation({
      query: (body) => ({
        url: "/api/member/resetpassword", // ← change if your actual path differs
        method: "POST",
        body,
      }),
    }),
    resetNewPassword: builder.mutation({
      query: (body) => ({
        url: "/api/member/resetnewpassword", // ← change if your actual path differs
        method: "POST",
        body,
      }),
    }),

    changeNewPassword: builder.mutation({
      query: (body) => ({
        url: "/api/member/changepassword", // ← change if your actual path differs
        method: "POST",
        body,
      }),
    }),

    deletePlayer: builder.mutation({
      query: (body) => ({
        url: "/api/member/player/delete",
        method: "POST",
        body,
      }),
    }),

    changeGamePassword: builder.mutation({
      query: (body) => ({
        url: "/api/member/player/changepassword",
        method: "POST",
        body,
      }),
    }),

    getReferralQr: builder.query({
      query: (body) => ({
        url: "/api/member/referral/qr",
        method: "POST",
        body,
      }),
    }),

    passwordOtp: builder.mutation({
      query: (body) => ({
        url: "/api/member/passwordOTP",
        method: "POST",
        body,
      }),
    }),

    sendPasswordOtp: builder.mutation({
      query: (body) => ({
        url: "/api/member/changepassword/send/otp",
        method: "POST",
        body,
      }),
    }),

    createDirectDownline: builder.mutation({
      query: (payload) => ({
        url: "/api/member/performance/downline/new",
        method: "POST",
        body: payload,
      }),
    }),

    fastOpenGame: builder.mutation({
      query: (body) => ({
        // url: "/api/member/player/list/add/reload/login",
        url: "/api/member/player/list/add/reload/v2/login",
        method: "POST",
        body,
      }),
    }),

    sendRandomUserOtp: builder.mutation({
      query: (body) => ({
        url: "/api/member/bind/phone/random",
        method: "POST",
        body,
      }),
    }),

    bindRandomUserOtp: builder.mutation({
      query: (body) => ({
        url: "/api/member/bind/phone/random/otp",
        method: "POST",
        body,
      }),
    }),

    // register: builder.mutation({
    //   query: (payload) => ({
    //     url: "/Member/Register",
    //     method: "POST",
    //     body: payload,
    //   }),
    // }),
  }),

  overrideExisting: false,
});

export const {
  useGenerateGoogle2faQrQuery,
  useVerifyGoogle2faMutation,
  useLoginMutation,
  useRegisterMutation,
  useGetPlayerListQuery,
  useLazyGetPlayerListQuery,
  useGetMemberViewQuery,
  useLazyGetMemberViewQuery,

  useGetPlayerViewQuery,
  useLazyGetPlayerViewQuery,

  useGetPlayerPasswordQuery,
  useAddPlayerMutation,
  useBindEmailMutation,
  useUpdateProfileMutation,
  useVerifyPhoneForResetMutation,
  useResetNewPasswordMutation,
  useChangeNewPasswordMutation,
  useDeletePlayerMutation,
  useChangeGamePasswordMutation,
  useGetReferralQrQuery,
  usePasswordOtpMutation,
  useSendPasswordOtpMutation,
  useCreateDirectDownlineMutation,
  useFastOpenGameMutation,
  useSendRandomUserOtpMutation,
  useBindRandomUserOtpMutation,
} = authApi;
