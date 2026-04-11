import type {
  ApiResponse,
  PaginationParams,
  PaginatedData,
} from "@/types/api-response.ts";
import type {
  Video,
  VideoInfo,
  PlayLogVideo,
  PurchaseVideoPayload,
  PurchaseVideoResponse,
  HotlistItem,
  VideoAccessRequest,
  VideoAccessResponse,
} from "@/types/video.types.ts";
import type { VideoPurchase } from "@/types/transaction.types.ts";
import type { Tags } from "@/types/tag.types.ts";
import type {
  CollectVideoPayload,
  CollectVideoResponse,
} from "@/types/collect.types.ts";
import axios from "@/lib/axios";

export const fetchVideoIndexList = async (
  body?: object,
  signal?: AbortSignal,
): Promise<ApiResponse<PaginatedData<Video>>> => {
  const response = await axios.post("/video/indexLists", body, { signal });
  return response.data;
};

export const fetchCategorizedVideoList = async (
  body: object,
  signal?: AbortSignal,
): Promise<ApiResponse<PaginatedData<Tags>>> => {
  const response = await axios.post("/tag/homeList", body, { signal });
  return response.data;
};

export const fetchVideoList = async (
  body: object,
  signal?: AbortSignal,
): Promise<ApiResponse<PaginatedData<Video>>> => {
  const response = await axios.post("/video/lists", body, { signal });
  return response.data;
};

export const fetchVideoHotList = async (
  body: unknown,
  signal?: AbortSignal,
): Promise<ApiResponse<PaginatedData<Video>>> => {
  const response = await axios.post("/video/hotLists", body, { signal });
  return response.data;
};

export const fetchVideoInfo = async (
  body: unknown,
  signal?: AbortSignal,
  lang?: string,
): Promise<ApiResponse<VideoInfo>> => {
  const response = await axios.post("/video/info", body, {
    signal,
    headers: lang ? { lang } : undefined,
  });
  const data = response.data;

  // Normalize the response to ensure publisher is always an object
  if (data.data && typeof data.data.publisher === "string") {
    data.data.publisher = {
      id: 0,
      name: data.data.publisher,
    };
  }

  return data;
};

export const fetchVideoUrl = async (
  body: object,
  signal?: AbortSignal,
): Promise<ApiResponse<string>> => {
  const response = await axios.post("/video/getVideoUrl", body, { signal });
  return response.data;
};

export const collectVideo = async (
  payload: CollectVideoPayload,
): Promise<ApiResponse<CollectVideoResponse>> => {
  const response = await axios.post("/video/collect", payload);
  return response.data;
};

export const fetchPlayLog = async (
  signal?: AbortSignal,
): Promise<ApiResponse<PaginatedData<PlayLogVideo>>> => {
  const response = await axios.post("/video/myPlayLog", {}, { signal });
  return response.data;
};

export const fetchMyCollectedVideos = async (
  signal?: AbortSignal,
): Promise<ApiResponse<PaginatedData<Video>>> => {
  const response = await axios.post("/video/myCollect", {}, { signal });
  return response.data;
};

export const purchaseVideo = async (
  payload: PurchaseVideoPayload,
  signal?: AbortSignal,
): Promise<ApiResponse<PurchaseVideoResponse>> => {
  const response = await axios.post("/video/purchase", payload, { signal });
  return response.data;
};

export const fetchHotlistLists = async (
  body: PaginationParams = {},
  signal?: AbortSignal,
): Promise<ApiResponse<PaginatedData<HotlistItem>>> => {
  const response = await axios.post("/hotlist/lists", body, { signal });
  return response.data;
};

export const fetchHotlistDetail = async (
  hid: number,
  signal?: AbortSignal,
): Promise<ApiResponse<HotlistItem>> => {
  const response = await axios.post("/hotlist/details", { hid }, { signal });
  return response.data;
};

export const fetchVideoPurchases = async (
  signal?: AbortSignal,
): Promise<ApiResponse<PaginatedData<VideoPurchase>>> => {
  const response = await axios.post("/video/myPurchase", {}, { signal });
  return response.data;
};

export const checkVideoAccess = async (
  payload: VideoAccessRequest,
  signal?: AbortSignal,
): Promise<ApiResponse<VideoAccessResponse>> => {
  const response = await axios.post("/video/userHasVideoAccess", payload, {
    signal,
  });
  return response.data;
};
