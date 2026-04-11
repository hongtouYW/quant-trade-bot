import type { ApiResponse } from "@/types/api-response.ts";
import type { SearchResultData } from "@/types/search.types.ts";
import axios from "@/lib/axios";

export const fetchGlobalSearch = async (
  keyword: string,
  signal?: AbortSignal,
): Promise<ApiResponse<SearchResultData>> => {
  const response = await axios.post("/index/globalSearch", { keyword }, { signal });
  return response.data;
};
