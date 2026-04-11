import { http } from ".";
import type {
  BannersType,
  CategoriesType,
  ComicDetailType,
  ComicHomepageLists,
  TagsType,
} from "../utils/type";

import { API_ENDPOINTS } from "./api-endpoint";
import type { APIResponsePaginationType, APIResponseType } from "./type";

// 获取横幅列表
export const getBannerLists = async (position: string) => {
  const res = await http.post<APIResponseType<BannersType[]>>(
    API_ENDPOINTS.bannerLists,
    {
      position: position,
    }
  );

  return res?.data?.data;
};

// 获取公告列表
export const getNotice = async () => {
  const res = await http.post<APIResponseType<any>>(API_ENDPOINTS.notice);

  return res?.data?.data || null;
};

// 获取标签列表
export const getTags = async () => {
  const res = await http.post<APIResponseType<TagsType[]>>(
    API_ENDPOINTS.comicTags
  );
  return res?.data?.data;
};

// 获取分类/题材列表
export const getCategories = async () => {
  const res = await http.post<APIResponseType<CategoriesType[]>>(
    API_ENDPOINTS.comicTicai
  );
  return res?.data?.data;
};

// 获取排行榜列表
// type: 1:畅销热卖 2:人气榜 3:完结榜 4:订阅榜
export const getComicRank = async (params: { type: string }) => {
  const res = await http.post<APIResponsePaginationType<ComicDetailType[]>>(
    API_ENDPOINTS.comicRank,
    params
  );
  return res?.data?.data;
};

// 获取排行榜列表
export const getComicAllRank = async (params: { range: string }) => {
  const res = await http.post<APIResponsePaginationType<any>>(
    API_ENDPOINTS.comicRanks,
    params
  );

  return res?.data?.data;
};

// 获取首页漫画列表
// type: 1:最近更新 3:精选推荐 4:限免推荐
export const getComicIndexLists = async (params: { type: string }) => {
  const res = await http.post<APIResponsePaginationType<ComicDetailType[]>>(
    API_ENDPOINTS.comicIndexLists,
    params
  );
  return res?.data?.data;
};

// 获取分类漫画列表
// ticai_id: 0 = 全部
export const getComicLists = async (params: {
  ticai_id?: string;
  page: number;
  limit: number;
}) => {
  const res = await http.post<APIResponsePaginationType<ComicDetailType[]>>(
    API_ENDPOINTS.comicLists,
    params
  );
  return res?.data?.data;
};

// 获取猜你喜欢
export const getComicRandom = async () => {
  const res = await http.post<APIResponseType>(API_ENDPOINTS.comicGuess, {
    mid: 0,
  });
  return res?.data?.data;
};

// 获取漫画详情
export const getComicInfo = async (params: {
  mid: string;
  page?: number;
  limit?: number;
  sort?: string;
}) => {
  const res = await http.post<
    APIResponseType<{ comic: ComicDetailType; chapters: any }>
  >(API_ENDPOINTS.comicInfo, params);

  return res?.data?.data;
};

// 获取章节信息
export const getComicChapterInfo = async (params: { cid: string }) => {
  const res = await http.post<APIResponseType<any>>(
    API_ENDPOINTS.comicChapterInfo,
    params
  );
  return res?.data?.data;
};

// 获取章节状态
export const getComicCheckChapterStatus = async (params: {
  token: string;
  mid: string;
}) => {
  const res = await http.post<APIResponseType<any>>(
    API_ENDPOINTS.comicCheckChaptersStatus,
    params
  );
  return res?.data?.data || null;
};

// 添加收藏
export const addComicFavorite = async (params: {
  token: string;
  mid: string;
}) => {
  const res = await http.post<APIResponseType>(
    API_ENDPOINTS.comicFavorite,
    params
  );
  return res;
};

// 取消收藏
export const removeComicFavorite = async (params: {
  token: string;
  mid: string;
}) => {
  const res = await http.post<APIResponseType>(
    API_ENDPOINTS.comicUnfavorite,
    params
  );
  return res;
};

// 购买章节
export const purchaseComicChapter = async (params: {
  token: string;
  cid: string;
}) => {
  const res = await http.post<APIResponseType>(
    API_ENDPOINTS.comicChapterBuy,
    params
  );
  return res;
};

export const getComicHomepage = async (params: { page: number, limit: number }) => {
  const res = await http.post<APIResponseType<ComicHomepageLists>>(
    API_ENDPOINTS.comicHomepage,
    params
  );
  return res?.data?.data;
};
