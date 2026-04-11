import { useQuery, useInfiniteQuery, type InfiniteData } from "@tanstack/react-query";
import { fetchActorRanking } from "@/services/actor.service.ts";
import type { ApiResponse, PaginatedData } from "@/types/api-response.ts";
import type { ActorRanking } from "@/types/actor.types.ts";

export const useActorRanking = (payload: object) =>
  useQuery({
    queryKey: ["actorRanking", JSON.stringify(payload)],
    queryFn: ({ signal }) => fetchActorRanking(payload, signal),
    staleTime: 1000 * 60 * 5,
  });

export const useInfiniteActorRanking = (basePayload: object) =>
  useInfiniteQuery<ApiResponse<PaginatedData<ActorRanking>>, Error, InfiniteData<ApiResponse<PaginatedData<ActorRanking>>>, (string | object)[], number>({
    queryKey: ["infiniteActorRanking", JSON.stringify(basePayload)],
    queryFn: ({ pageParam = 1, signal }) =>
      fetchActorRanking({
        ...basePayload,
        page: pageParam as number,
      }, signal),
    getNextPageParam: (lastPage: ApiResponse<PaginatedData<ActorRanking>>) => {
      if (lastPage.data.current_page < lastPage.data.last_page) {
        return lastPage.data.current_page + 1;
      }
      return undefined;
    },
    initialPageParam: 1,
    staleTime: 1000 * 60 * 5,
    refetchOnWindowFocus: false,
  });
