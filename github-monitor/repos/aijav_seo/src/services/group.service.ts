import type { ApiResponse, PaginatedData } from "@/types/api-response.ts";
import type {
  GroupDetailResponse,
  CollectedGroup,
  CollectionToggleRequest,
  CollectionToggleResponse,
  GroupPurchaseRequest,
  GroupPurchaseResponse
} from "@/types/group.types.ts";
import type { SeriesPurchase } from "@/types/transaction.types.ts";
import axios from "@/lib/axios";

export const fetchGroupList = async (
  payload: object,
  signal?: AbortSignal,
): Promise<ApiResponse<PaginatedData<GroupDetailResponse>>> => {
  const response = await axios.post("/group/lists", payload, { signal });
  return response.data;
};

export const fetchGroupDetail = async (
  gid: number,
  signal?: AbortSignal,
): Promise<ApiResponse<GroupDetailResponse>> => {
  const response = await axios.post("/group/details", { gid }, { signal });
  return response.data;
};

export const fetchMyCollectedGroups = async (signal?: AbortSignal): Promise<ApiResponse<PaginatedData<CollectedGroup>>> => {
  const response = await axios.post("/group/myCollect", {}, { signal });
  return response.data;
};

export const toggleGroupCollection = async (
  payload: CollectionToggleRequest,
): Promise<ApiResponse<CollectionToggleResponse>> => {
  const response = await axios.post("/group/collect", payload);
  return response.data;
};

export const purchaseGroup = async (
  payload: GroupPurchaseRequest,
): Promise<ApiResponse<GroupPurchaseResponse>> => {
  const response = await axios.post("/group/purchase", payload);
  return response.data;
};

export const fetchSeriesPurchases = async (signal?: AbortSignal): Promise<ApiResponse<PaginatedData<SeriesPurchase>>> => {
  const response = await axios.post("/group/myPurchase", {}, { signal });
  return response.data;
};
