import { keepPreviousData, useQuery } from "@tanstack/react-query";
import { fetchVideoList } from "@/services/video.service.ts";
import type { PaginationParams } from "@/types/api-response.ts";

interface UseVideoIndexListParams extends PaginationParams {
  type?: string;
  tag_id?: string;
  random?: number;
}

export const useVideoIndexList = (params?: UseVideoIndexListParams) => {
  const { page = 1, limit = 20, type, ...otherParams } = params || {};

  return useQuery({
    queryKey: ["videoIndexList", page, limit, type, params?.tag_id],
    queryFn: ({ signal }) => {
      const payload = { page, limit, type, ...otherParams };

      return fetchVideoList(payload, signal);
    },
    staleTime: 1000 * 60 * 5, // 5 minutes
    gcTime: 1000 * 60 * 30, // 30 minutes garbage collection
    // Performance optimizations
    refetchOnWindowFocus: false,
    refetchOnMount: false,
    // Enable background refetching for smooth UX
    refetchOnReconnect: true,
    // Keep previous data while loading new page for smooth UX
    placeholderData: keepPreviousData,
  });
};
