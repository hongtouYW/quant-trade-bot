import type { ApiResponse, PaginatedData } from "@/types/api-response.ts";
import type { User } from "@/types/user.types.ts";
import type { LoginUserPayload, RegisterUserPayload, RedeemCodePayload, RedeemCodeResponse, RedeemRecord, QuickRegisterResponse } from "@/types/user.types";
import axios from "@/lib/axios";

export const loginUser = async (
  payload: LoginUserPayload,
  signal?: AbortSignal,
): Promise<ApiResponse<User>> => {
  const response = await axios.post("/user/login", payload, { signal });
  return response.data;
};

export const fetchUserInfo = async (signal?: AbortSignal): Promise<ApiResponse<User>> => {
  const response = await axios.post("/user/info", {}, { signal });
  return response.data;
};

export const registerUser = async (
  payload: RegisterUserPayload,
  signal?: AbortSignal,
): Promise<ApiResponse<User>> => {
  const response = await axios.post("/user/register", payload, { signal });
  return response.data;
};

export const redeemCode = async (
  payload: RedeemCodePayload,
  signal?: AbortSignal,
): Promise<ApiResponse<RedeemCodeResponse>> => {
  const response = await axios.post("/user/redeemcode", payload, { signal });
  return response.data;
};

export const submitFeedback = async (
  payload: { title: string; content: string },
  signal?: AbortSignal,
): Promise<ApiResponse<any>> => {
  const response = await axios.post("/user/feedback", payload, { signal });
  return response.data;
};

export const fetchRedeemRecord = async (
  signal?: AbortSignal,
): Promise<ApiResponse<PaginatedData<RedeemRecord>>> => {
  const response = await axios.post("/user/redeem_record", {}, { signal });
  return response.data;
};

export const quickRegisterUser = async (
  signal?: AbortSignal,
): Promise<QuickRegisterResponse> => {
  const response = await axios.post("/user/quickRegister", {}, { signal });
  return response.data;
};
