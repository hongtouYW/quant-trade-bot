import type { ApiResponse } from "@/types/api-response.ts";
import type { Notice } from "@/types/notice.types.ts";
import axios from "@/lib/axios";

export const fetchNotices = async (): Promise<ApiResponse<Notice[]>> => {
  const response = await axios.post("/notice/lists");
  return response.data;
};