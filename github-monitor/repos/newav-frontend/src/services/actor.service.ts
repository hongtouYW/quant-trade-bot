import type { ApiResponse, PaginatedData } from "@/types/api-response.ts";
import type {
  ActorByPublisherPayload,
  ActorInfo,
  ActorList,
  ActorRanking,
  ActorSubscribePayload,
  MyActorResponse,
} from "@/types/actor.types.ts";
import axios from "@/lib/axios";
import type { SubscribeResponse } from "@/types/search.types.ts";

export const fetchActorList = async (
  payload: object,
  signal?: AbortSignal,
): Promise<ApiResponse<PaginatedData<ActorList>>> => {
  const response = await axios.post("/actor/lists", payload, { signal });
  return response.data;
};
export const fetchActorListByPublisher = async (
  payload: ActorByPublisherPayload,
  signal?: AbortSignal,
): Promise<ApiResponse<PaginatedData<ActorList>>> => {
  const response = await axios.post("/actor/byPublisher", payload, { signal });
  return response.data;
};

export const fetchActorInfo = async (
  actorId: string,
  signal?: AbortSignal,
): Promise<ApiResponse<ActorInfo>> => {
  const response = await axios.post("/actor/info", { aid: actorId }, { signal });
  return response.data;
};

export const fetchSubscribedActorList = async (
  signal?: AbortSignal,
  payload?: { page?: number; limit?: number },
): Promise<
  ApiResponse<PaginatedData<MyActorResponse>>
> => {
  const response = await axios.post("/actor/mySubscribe", payload || {}, { signal });
  return response.data;
};

export const subscribeToActor = async (
  payload: ActorSubscribePayload,
): Promise<ApiResponse<SubscribeResponse>> => {
  const response = await axios.post("/actor/subscribe", payload);
  return response.data;
};

export const fetchActorRanking = async (
  payload: object,
  signal?: AbortSignal,
): Promise<ApiResponse<PaginatedData<ActorRanking>>> => {
  const response = await axios.post("/actor/actorRanking", payload, { signal });
  return response.data;
};

