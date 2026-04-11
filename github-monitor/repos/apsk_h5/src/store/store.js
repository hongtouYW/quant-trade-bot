// src/store.js
import { configureStore } from "@reduxjs/toolkit";
import { baseApi } from "@/services/baseApi";
import otpVerifySlice from "./slice/otpVerifySlice";
import emailVerifySlice from "./slice/emailVerifySlice";
import bankDetailSlice from "./slice/bankDetailSlice";
import transactionDetailSlice from "./slice/transactionDetailSlice";
import resetPasswordSlice from "./slice/resetPwdSlice";
import changePwdReducer from "./slice/changePwdSlice";
import helpSlice from "./slice/helpSlice";
import promotionSlice from "./slice/promotionSlice";
import transactionListSlice from "./slice/transactionListSlice";
import transactionFilterSlice from "./slice/transactionFilterSlice";
export const store = configureStore({
  reducer: {
    transactionList: transactionListSlice,
    otpVerify: otpVerifySlice,
    emailVerify: emailVerifySlice,
    transactionDetail: transactionDetailSlice,
    bankDetail: bankDetailSlice,
    resetPwd: resetPasswordSlice,
    promotion: promotionSlice,
    changePwd: changePwdReducer,
    help: helpSlice,
    transactionFilter: transactionFilterSlice,
    [baseApi.reducerPath]: baseApi.reducer,
  },
  middleware: (getDefaultMiddleware) =>
    getDefaultMiddleware().concat(baseApi.middleware),
});
