import { useQuery } from "@tanstack/react-query";
import { fetchVideoList } from "@/services/video.service.ts";

export const useVideoList = (payload: object, actorId?: number) =>
  useQuery({
    queryKey: ["rankedVideoList", actorId, payload],
    queryFn: ({ signal }) => {
      return fetchVideoList(payload, signal);
    },
    select: (data) => {
      return data;
    },
    refetchOnWindowFocus: false,
    placeholderData: (previousData) => previousData,
  });
