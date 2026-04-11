import type { ApiResponse, PaginatedData } from "@/types/api-response.ts";
import type { Category } from "@/types/categories.ts";
import axios from "@/lib/axios";

export const fetchCategoryList = async (
  payload: object = {},
  signal?: AbortSignal,
): Promise<ApiResponse<PaginatedData<Category>>> => {
  const response = await axios.post("/category/lists", payload, { signal });
  return response.data;
};
