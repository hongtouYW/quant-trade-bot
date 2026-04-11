import { useQuery, useInfiniteQuery, type InfiniteData } from "@tanstack/react-query";
import { fetchActorList } from "@/services/actor.service.ts";
import type { ApiResponse, PaginatedData } from "@/types/api-response.ts";
import type { ActorList } from "@/types/actor.types.ts";

export const useActorList = (payload: object, order: number) =>
  useQuery({
    queryKey: ["actorList", order],
    queryFn: ({ signal }) => fetchActorList(payload, signal),
    staleTime: 1000 * 60 * 5,
  });

export const useInfiniteActorList = (basePayload: object, order: number) =>
  useInfiniteQuery<ApiResponse<PaginatedData<ActorList>>, Error, InfiniteData<ApiResponse<PaginatedData<ActorList>>>, (string | number)[], number>({
    queryKey: ["infiniteActorList", order, JSON.stringify(basePayload)],
    queryFn: ({ pageParam = 1, signal }) =>
      fetchActorList({
        ...basePayload,
        page: pageParam as number,
      }, signal),
    getNextPageParam: (lastPage: ApiResponse<PaginatedData<ActorList>>) => {
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
