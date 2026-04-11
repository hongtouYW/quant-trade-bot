import { http } from ".";
import { API_ENDPOINTS } from "./api-endpoint";
import type { APIResponseType } from "./type";

// 添加阅读记录
export const addUserHistory = async (params: {
  token: string;
  cid: string;
}) => {
  const res = await http.post<APIResponseType>(
    API_ENDPOINTS.userAddHistory,
    params
  );
  return res;
};

// 获取用户信息
export const getUserInfo = async (params: { token: string }) => {
  const res = await http.post<APIResponseType>(API_ENDPOINTS.userInfo, params);
  return res;
};

// 获取用户收藏
export const getUserFavorite = async (params: { token: string }) => {
  const res = await http.post<APIResponseType>(API_ENDPOINTS.userFavorite, params);

  return res?.data?.data;
};

export const submitUserFeedback = async (params: {
  token?: string;
  satisfaction: 1 | 2 | 3; // 1: 满意 | 2: 一般 | 3: 不满意
  content: string;
  contact?: string;
}) => {
  const res = await http.post<APIResponseType>(
    API_ENDPOINTS.userFeedback,
    params
  );
  return res;
};
