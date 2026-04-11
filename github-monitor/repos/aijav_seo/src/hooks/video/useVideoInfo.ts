import { useQuery } from "@tanstack/react-query";
import { fetchVideoInfo } from "@/services/video.service.ts";
import type { ApiResponse } from "@/types/api-response.ts";
import type { VideoInfo } from "@/types/video.types.ts";

export const useVideoInfo = (
  videoId: string | undefined,
  initialData?: ApiResponse<VideoInfo>,
) =>
  useQuery({
    queryKey: ["videoInfo", videoId],
    queryFn: ({ signal }) =>
      fetchVideoInfo({
        vid: Number(videoId),
      }, signal),
    enabled: !!videoId,
    initialData,
  });
