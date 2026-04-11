import { useInfiniteQuery, type InfiniteData } from "@tanstack/react-query";
import { fetchSubscribedActorList } from "@/services/actor.service.ts";
import type { ApiResponse, PaginatedData } from "@/types/api-response.ts";
import type { MyActorResponse } from "@/types/actor.types.ts";

export const useInfiniteSubscribedActorList = (limit: number = 30) =>
  useInfiniteQuery<ApiResponse<PaginatedData<MyActorResponse>>, Error, InfiniteData<ApiResponse<PaginatedData<MyActorResponse>>>, string[], number>({
    queryKey: ["infiniteSubscribedActorList"],
    queryFn: ({ pageParam = 1, signal }) =>
      fetchSubscribedActorList(signal, {
        page: pageParam as number,
        limit,
      }),
    getNextPageParam: (lastPage: ApiResponse<PaginatedData<MyActorResponse>>) => {
      if (lastPage.data.current_page < lastPage.data.last_page) {
        return lastPage.data.current_page + 1;
      }
      return undefined;
    },
    initialPageParam: 1,
    refetchOnWindowFocus: false,
  });
