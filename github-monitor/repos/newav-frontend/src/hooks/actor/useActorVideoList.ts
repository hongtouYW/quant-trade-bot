import { useQuery, useInfiniteQuery, type InfiniteData } from "@tanstack/react-query";
import { fetchVideoList } from "@/services/video.service.ts";
import type { ApiResponse, PaginatedData } from "@/types/api-response.ts";
import type { Video } from "@/types/video.types.ts";

export const useActorVideoList = (actorId: string) =>
  useQuery({
    queryKey: ["actorVideoList", actorId], // Include the param in query key
    queryFn: ({ signal }) =>
      fetchVideoList(
        {
          actor_id: actorId,
          page: "1",
          limit: 20,
        },
        signal,
      ),
    select: (data) => {
      return data.data;
    },
    enabled: !!actorId, // optional: avoids running if userId is falsy
  });

export const useInfiniteActorVideoList = (actorId: string) =>
  useInfiniteQuery<ApiResponse<PaginatedData<Video>>, Error, InfiniteData<ApiResponse<PaginatedData<Video>>>, string[], number>({
    queryKey: ["infiniteActorVideoList", actorId],
    queryFn: ({ pageParam = 1, signal }) =>
      fetchVideoList(
        {
          actor_id: actorId,
          page: (pageParam as number).toString(),
          limit: 20,
        },
        signal,
      ),
    getNextPageParam: (lastPage: ApiResponse<PaginatedData<Video>>) => {
      // Check if there are more pages based on pagination structure
      if (lastPage.data.current_page < lastPage.data.last_page) {
        return lastPage.data.current_page + 1;
      }
      return undefined;
    },
    initialPageParam: 1,
    enabled: !!actorId,
    refetchOnWindowFocus: false,
  });

export const usePublisherVideoList = (publisherId: string) =>
  useQuery({
    queryKey: ["publisherVideoList", publisherId], // Include the param in query key
    queryFn: ({ signal }) =>
      fetchVideoList(
        {
          publisher_id: publisherId,
          page: "1",
          limit: 20,
        },
        signal,
      ),
    select: (data) => {
      return data.data;
    },
    enabled: !!publisherId, // optional: avoids running if userId is falsy
  });

export const useInfinitePublisherVideoList = (publisherId: string) =>
  useInfiniteQuery<ApiResponse<PaginatedData<Video>>, Error, InfiniteData<ApiResponse<PaginatedData<Video>>>, string[], number>({
    queryKey: ["infinitePublisherVideoList", publisherId],
    queryFn: ({ pageParam = 1, signal }) =>
      fetchVideoList(
        {
          publisher_id: publisherId,
          page: (pageParam as number).toString(),
          limit: 20,
        },
        signal,
      ),
    getNextPageParam: (lastPage: ApiResponse<PaginatedData<Video>>) => {
      // Check if there are more pages based on pagination structure
      if (lastPage.data.current_page < lastPage.data.last_page) {
        return lastPage.data.current_page + 1;
      }
      return undefined;
    },
    initialPageParam: 1,
    enabled: !!publisherId,
    refetchOnWindowFocus: false,
  });

export const useInfiniteActorSeriesList = (actorId: string) =>
  useInfiniteQuery<ApiResponse<PaginatedData<Video>>, Error, InfiniteData<ApiResponse<PaginatedData<Video>>>, string[], number>({
    queryKey: ["infiniteActorSeriesList", actorId],
    queryFn: ({ pageParam = 1, signal }) =>
      fetchVideoList(
        {
          actor_id: actorId,
          sequel: 1,
          page: (pageParam as number).toString(),
          limit: 20,
        },
        signal,
      ),
    getNextPageParam: (lastPage: ApiResponse<PaginatedData<Video>>) => {
      // Check if there are more pages based on pagination structure
      if (lastPage.data.current_page < lastPage.data.last_page) {
        return lastPage.data.current_page + 1;
      }
      return undefined;
    },
    initialPageParam: 1,
    enabled: !!actorId,
    refetchOnWindowFocus: false,
  });
