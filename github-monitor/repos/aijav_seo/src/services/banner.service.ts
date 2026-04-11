import type { ApiResponse } from "@/types/api-response.ts";
import type { Banner } from "@/types/banner.ts";
import axios from "@/lib/axios";

export const fetchBannerList = async (signal?: AbortSignal): Promise<ApiResponse<Banner[]>> => {
  const response = await axios.post("/banner/lists", {}, { signal });
  return response.data;
};
