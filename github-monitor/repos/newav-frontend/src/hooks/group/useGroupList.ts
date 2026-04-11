import { useQuery, useInfiniteQuery, type InfiniteData } from "@tanstack/react-query";
import { fetchGroupList } from "@/services/group.service.ts";
import type { ApiResponse, PaginatedData } from "@/types/api-response.ts";
import type { GroupDetailResponse } from "@/types/group.types.ts";

export const useGroupList = (payload: object) =>
  useQuery({
    queryKey: ["groupList"],
    queryFn: ({ signal }) => fetchGroupList(payload, signal),
    select: (data) => ({
      data: data.data.data, // the array of items
      pagination: {
        total: data.data.total,
        per_page: data.data.per_page,
        current_page: data.data.current_page,
        last_page: data.data.last_page,
      }
    }),
    // staleTime: 1000 * 60 * 5,
  });

export const useInfiniteGroupList = (basePayload: object) =>
  useInfiniteQuery<ApiResponse<PaginatedData<GroupDetailResponse>>, Error, InfiniteData<ApiResponse<PaginatedData<GroupDetailResponse>>>, string[], number>({
    queryKey: ["infiniteGroupList", JSON.stringify(basePayload)],
    queryFn: ({ pageParam = 1, signal }) =>
      fetchGroupList({
        ...basePayload,
        page: pageParam as number,
      }, signal),
    getNextPageParam: (lastPage: ApiResponse<PaginatedData<GroupDetailResponse>>) => {
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

export const useInfinitePublisherGroupList = (publisherId: string) =>
  useInfiniteQuery<ApiResponse<PaginatedData<GroupDetailResponse>>, Error, InfiniteData<ApiResponse<PaginatedData<GroupDetailResponse>>>, string[], number>({
    queryKey: ["infinitePublisherGroupList", publisherId],
    queryFn: ({ pageParam = 1, signal }) =>
      fetchGroupList({
        publisher_id: Number(publisherId),
        page: pageParam as number,
        limit: 20,
      }, signal),
    getNextPageParam: (lastPage: ApiResponse<PaginatedData<GroupDetailResponse>>) => {
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
