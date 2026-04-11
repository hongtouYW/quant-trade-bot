import type { ApiResponse } from "@/types/api-response.ts";
import type { GlobalImageResponse } from "@/types/global-image.types.ts";
import axios from "@/lib/axios";

export const fetchGlobalImage = async (
  signal?: AbortSignal,
): Promise<ApiResponse<GlobalImageResponse>> => {
  const response = await axios.post("/index/globalImage", {}, { signal });
  return response.data;
};
