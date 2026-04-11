import {
  useQuery,
  useInfiniteQuery,
  type InfiniteData,
} from "@tanstack/react-query";
import { fetchVideoList } from "@/services/video.service.ts";
import type { ApiResponse, PaginatedData } from "@/types/api-response.ts";
import type { Video } from "@/types/video.types.ts";

type SelectedMonthVideoListParams = {
  page: string;
  limit: number;
  publish_date: string;
};

export const useSelectedMonthVideoList = (selectedMonth: string) =>
  useQuery({
    queryKey: ["selectedMonthVideoList", selectedMonth], // Include the param in query key
    queryFn: ({ signal }) => {
      const params: SelectedMonthVideoListParams = {
        page: "1",
        limit: 30,
        publish_date: selectedMonth,
      };

      return fetchVideoList(params, signal);
    },
    select: (data) => {
      return data.data;
    },
    enabled: !!selectedMonth,
  });

export const useInfiniteSelectedMonthVideoList = (selectedMonth: string) => {
  return useInfiniteQuery<
    ApiResponse<PaginatedData<Video>>,
    Error,
    InfiniteData<ApiResponse<PaginatedData<Video>>>,
    string[],
    number
  >({
    queryKey: ["infiniteSelectedMonthVideoList", selectedMonth],
    queryFn: ({ pageParam = 1, signal }) => {
      const params: SelectedMonthVideoListParams = {
        page: (pageParam as number).toString(),
        limit: 30,
        publish_date: selectedMonth,
      };

      return fetchVideoList(params, signal);
    },
    getNextPageParam: (lastPage: ApiResponse<PaginatedData<Video>>) => {
      // Check if there are more pages based on pagination structure
      if (lastPage.data.current_page < lastPage.data.last_page) {
        return lastPage.data.current_page + 1;
      }
      return undefined;
    },
    initialPageParam: 1,
    enabled: !!selectedMonth,
    refetchOnWindowFocus: false,
    staleTime: Infinity,
  });
};
