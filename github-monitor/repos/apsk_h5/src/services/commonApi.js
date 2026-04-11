// src/services/authApi.js
import { baseApi } from "./baseApi";

export const commonApi = baseApi.injectEndpoints({
  endpoints: (builder) => ({
    getCountryPhone: builder.query({
      query: (body) => ({
        url: "/api/country/list/phone",
        method: "POST",
        body,
      }),
    }),

    getAvatarList: builder.query({
      query: (body) => ({
        url: "/api/member/avatar/list",
        method: "POST",
        body,
      }),
    }),

    getQuestionList: builder.query({
      query: (body) => ({
        url: "/api/member/question/list",
        method: "POST",
        body,
      }),
    }),

    getVersionList: builder.query({
      query: (body) => ({
        url: "/api/member/version/list",
        method: "POST",
        body,
      }),
      refetchOnMountOrArgChange: false,
      refetchOnFocus: false,
      refetchOnReconnect: false,
      keepUnusedDataFor: 0,
    }),

    getSupportList: builder.query({
      query: (body) => ({
        url: "/api/member/support/link",
        method: "POST",
        body,
      }),
    }),

    getAgreementList: builder.query({
      query: () => ({
        url: "/api/agreement/view",
        method: "POST",
        body: {},
      }),
    }),

    getOfficialLink: builder.query({
      query: (body) => ({
        url: "/api/member/official/link",
        method: "POST",
        body,
      }),
    }),

    getDashboardMarquee: builder.query({
      query: (body) => ({
        url: "/api/member/dashboard",
        method: "POST",
        body,
      }),
      refetchOnMountOrArgChange: 300,
      refetchOnFocus: false,
      refetchOnReconnect: false,
      keepUnusedDataFor: 3600,
    }),

    getSliderList: builder.query({
      query: (body) => ({
        url: "/api/member/slider/list",
        method: "POST",
        body,
      }),
    }),

    markSliderRead: builder.mutation({
      query: (body) => ({
        url: "/api/member/slider/read",
        method: "POST",
        body,
      }),
    }),

    getFeedbackTypeList: builder.query({
      query: (body) => ({
        url: "/api/member/feedback/type/list",
        method: "POST",
        body,
      }),
    }),

    getAgentIcon: builder.query({
      query: (body) => ({
        url: "/api/member/agent/icon",
        method: "POST",
        body,
      }),
    }),
  }),

  overrideExisting: false,
});

export const {
  useLazyGetCountryPhoneQuery,
  useGetCountryPhoneQuery,
  useGetAvatarListQuery,
  useGetSupportListQuery,
  useGetQuestionListQuery,
  useGetVersionListQuery,
  useGetAgreementListQuery,
  useGetDashboardMarqueeQuery,
  useGetSliderListQuery,
  useGetOfficialLinkQuery,
  useMarkSliderReadMutation,
  useGetFeedbackTypeListQuery,
  useGetAgentIconQuery,
} = commonApi;
