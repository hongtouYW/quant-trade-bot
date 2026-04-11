import { useQuery, useInfiniteQuery, type InfiniteData } from "@tanstack/react-query";
import { fetchPublisherList } from "@/services/publisher.service.ts";
import type { ApiResponse, PaginatedData } from "@/types/api-response.ts";
import type { PublisherResult } from "@/types/search.types.ts";

export const usePublisherList = (payload: object) =>
  useQuery({
    queryKey: ["publisherList"],
    queryFn: ({ signal }) => fetchPublisherList(payload, signal),
    // staleTime: 1000 * 60 * 5,
  });

export const useInfinitePublisherList = (basePayload: object) =>
  useInfiniteQuery<ApiResponse<PaginatedData<PublisherResult>>, Error, InfiniteData<ApiResponse<PaginatedData<PublisherResult>>>, string[], number>({
    queryKey: ["infinitePublisherList", JSON.stringify(basePayload)],
    queryFn: ({ pageParam = 1, signal }) =>
      fetchPublisherList({
        ...basePayload,
        page: pageParam as number,
      }, signal),
    getNextPageParam: (lastPage: ApiResponse<PaginatedData<PublisherResult>>) => {
      // Check if there are more pages based on pagination structure
      if (lastPage.data.current_page < lastPage.data.last_page) {
        return lastPage.data.current_page + 1;
      }
      return undefined;
    },
    initialPageParam: 1,
    staleTime: 1000 * 60 * 5,
    refetchOnWindowFocus: false,
  });
