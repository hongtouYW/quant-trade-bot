import { useInfiniteQuery, type InfiniteData } from "@tanstack/react-query";
import { fetchMyPublisherList } from "@/services/publisher.service.ts";
import type { ApiResponse, PaginatedData } from "@/types/api-response.ts";
import type { MyPublisherResponse } from "@/types/search.types.ts";

export const useInfiniteMyPublisherList = (limit: number = 30) =>
  useInfiniteQuery<ApiResponse<PaginatedData<MyPublisherResponse>>, Error, InfiniteData<ApiResponse<PaginatedData<MyPublisherResponse>>>, string[], number>({
    queryKey: ["infiniteMyPublisherList"],
    queryFn: ({ pageParam = 1, signal }) =>
      fetchMyPublisherList(signal, {
        page: pageParam as number,
        limit,
      }),
    getNextPageParam: (lastPage: ApiResponse<PaginatedData<MyPublisherResponse>>) => {
      if (lastPage.data.current_page < lastPage.data.last_page) {
        return lastPage.data.current_page + 1;
      }
      return undefined;
    },
    initialPageParam: 1,
    refetchOnWindowFocus: false,
  });
