import type { ApiResponse, PaginatedData } from "@/types/api-response.ts";
import type { TagListItem, TagListRequest } from "@/types/tag.types.ts";
import axios from "@/lib/axios";

export const fetchTagList = async (
  payload: TagListRequest,
  signal?: AbortSignal,
): Promise<ApiResponse<PaginatedData<TagListItem>>> => {
  const response = await axios.post("/tag/lists", payload, { signal });
  return response.data;
};