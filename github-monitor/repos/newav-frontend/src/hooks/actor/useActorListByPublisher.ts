import { useQuery, useInfiniteQuery, type InfiniteData } from "@tanstack/react-query";
import { fetchActorListByPublisher } from "@/services/actor.service.ts";
import type { ActorByPublisherPayload, ActorList } from "@/types/actor.types.ts";
import type { ApiResponse, PaginatedData } from "@/types/api-response.ts";

export const useActorListByPublisher = (payload: ActorByPublisherPayload) =>
  useQuery({
    queryKey: ["actorByPublisherList", payload.pid],
    queryFn: ({ signal }) => fetchActorListByPublisher(payload, signal),
    select: (data) => data.data,
  });

export const useInfiniteActorListByPublisher = (basePayload: ActorByPublisherPayload) =>
  useInfiniteQuery<ApiResponse<PaginatedData<ActorList>>, Error, InfiniteData<ApiResponse<PaginatedData<ActorList>>>, (string | number)[], number>({
    queryKey: ["infiniteActorByPublisherList", basePayload.pid],
    queryFn: ({ pageParam = 1, signal }) =>
      fetchActorListByPublisher({
        pid: basePayload.pid,
        page: pageParam as number,
      } as any, signal),
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
