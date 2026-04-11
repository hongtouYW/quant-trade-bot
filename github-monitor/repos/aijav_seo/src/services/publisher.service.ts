import type { ApiResponse, PaginatedData } from "@/types/api-response.ts";
import type {
  PublisherInfo,
  PublisherSubscribePayload,
} from "@/types/publisher.types.ts";
import type {
  MyPublisherResponse,
  PublisherResult,
  SubscribeResponse,
} from "@/types/search.types.ts";
import axios from "@/lib/axios";

export const fetchPublisherInfo = async (
  publisherId: string,
  signal?: AbortSignal,
  lang?: string,
): Promise<ApiResponse<PublisherInfo>> => {
  const response = await axios.post(
    "/publisher/info",
    { pid: publisherId },
    { signal, headers: lang ? { lang } : undefined },
  );
  return response.data;
};

export const fetchPublisherList = async (
  payload: object,
  signal?: AbortSignal,
): Promise<ApiResponse<PaginatedData<PublisherResult>>> => {
  const response = await axios.post("/publisher/lists", payload, { signal });
  return response.data;
};

export const fetchMyPublisherList = async (
  signal?: AbortSignal,
  payload?: { page?: number; limit?: number },
): Promise<
  ApiResponse<PaginatedData<MyPublisherResponse>>
> => {
  const response = await axios.post("/publisher/mySubscribe", payload || {}, { signal });
  return response.data;
};

export const subscribeToPublisher = async (
  payload: PublisherSubscribePayload,
): Promise<ApiResponse<SubscribeResponse>> => {
  const response = await axios.post("/publisher/subscribe", payload);
  return response.data;
};

