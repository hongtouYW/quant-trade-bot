import { useQuery } from "@tanstack/react-query";
import { fetchVideoUrl } from "@/services/video.service.ts";
import { useAuth } from "@/hooks/auth/useAuth.ts";

export const useVideoUrl = (
  videoId?: number,
  code?: number,
  hasAccess?: boolean,
  videoPrivate?: number,
) => {
  const { isAuthenticated } = useAuth();

  // For free videos (private === 0), authentication is not required
  const isFreeVideo = videoPrivate === 0;

  // Enable query only if videoId exists, API success (code === 1), and access is granted
  // For free videos, skip authentication requirement
  const enabled = !!videoId && code === 1 && hasAccess && (isAuthenticated || isFreeVideo);

  return useQuery({
    queryKey: ["videoUrl", videoId, hasAccess],
    queryFn: ({ signal }) => {
      if (!videoId || !code) throw new Error("videoId and code are required");
      if (!hasAccess) throw new Error("Video access not granted");

      return fetchVideoUrl(
        {
          vid: Number(videoId),
        },
        signal,
      );
    },
    select: (data) => {
      // Simply return the video URL data, all permission checks are handled by useVideoAccess
      return data.data;
    },
    enabled,
    retry: false,
    refetchOnWindowFocus: false,
  });
};
