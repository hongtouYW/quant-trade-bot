// src/services/authApi.js
import { baseApi } from "./baseApi";

export const otpApi = baseApi.injectEndpoints({
  endpoints: (builder) => ({
    otpVerify: builder.mutation({
      query: (body) => ({
        url: "/api/member/verifyOTP",
        method: "POST",
        body,
      }),
    }),

    emailVerify: builder.mutation({
      query: (body) => ({
        url: "/api/member/bind/email/otp",
        method: "POST",
        body,
      }),
    }),

    resetOtp: builder.mutation({
      query: (body) => ({
        url: "/api/member/bind/email/otp",
        method: "POST",
        body,
      }),
    }),

    resetPasswordOtp: builder.mutation({
      query: (body) => ({
        url: "/api/member/resetOTP",
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
  useOtpVerifyMutation,
  useEmailVerifyMutation,
  useResetOtpMutation,
  useResetPasswordOtpMutation,
} = otpApi;
