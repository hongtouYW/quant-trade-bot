import type { ApiResponse } from "@/types/api-response.ts";
import type { ConfigData } from "@/types/config.types.ts";
import axios from "@/lib/axios";

export const fetchConfig = async (signal?: AbortSignal): Promise<ApiResponse<ConfigData>> => {
  const response = await axios.post("/config/lists", {}, { signal });
  return response.data;
};