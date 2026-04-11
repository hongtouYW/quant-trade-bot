import { useQuery } from "@tanstack/react-query";
import { fetchVideoInfo } from "@/services/video.service.ts";

export const useVideoInfo = (videoId: string) =>
  useQuery({
    queryKey: ["videoInfo", videoId],
    queryFn: ({ signal }) =>
      fetchVideoInfo({
        vid: Number(videoId),
      }, signal),
    enabled: !!videoId,
  });
