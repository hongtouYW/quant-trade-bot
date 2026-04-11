import { useQuery } from "@tanstack/react-query";
import { checkVideoAccess } from "@/services/video.service";
import { useAuth } from "@/hooks/auth/useAuth";

export type VideoAccessErrorType = "auth" | "purchase" | "purchase-or-vip" | "series" | "server" | "vip-expired";

export const useVideoAccess = (videoId?: number, videoPrivate?: number) => {
  const { isAuthenticated } = useAuth();

  // For free videos (private === 0), grant access immediately without API call
  const isFreeVideo = videoPrivate === 0;

  const queryResult = useQuery({
    queryKey: ["videoAccess", videoId, isAuthenticated, videoPrivate],
    queryFn: async ({ signal }) => {
      if (!videoId) throw new Error("Video ID is required");

      // For free videos, skip authentication requirement and grant access
      if (isFreeVideo) {
        return { shouldShowOverlay: false, errorType: null };
      }

      if (!isAuthenticated) {
        return { shouldShowOverlay: true, errorType: "auth" as VideoAccessErrorType };
      }

      try {
        const response = await checkVideoAccess({ vid: videoId }, signal);

        // Handle successful access
        if (response.code === 1 && response.data.access) {
          return { shouldShowOverlay: false, errorType: null };
        }

        // Handle access denied with specific error codes
        const errorType: VideoAccessErrorType = response.code === 7001
          ? "vip-expired"
          : response.code === 6007
          ? "purchase"
          : response.code === 6008
          ? "purchase-or-vip"
          : response.code === 5007
          ? "series"
          : "server";

        return {
          shouldShowOverlay: true,
          errorType,
          errorMessage: response.data.msg || response.msg
        };

      } catch (error) {
        console.error("Video access check failed:", error);
        return {
          shouldShowOverlay: true,
          errorType: "server" as VideoAccessErrorType,
          errorMessage: "Failed to check video access"
        };
      }
    },
    enabled: !!videoId,
    refetchOnWindowFocus: false,
    retry: false,
  });

  // When query is disabled, return a default state with isPending: false
  if (!videoId) {
    return {
      data: undefined,
      error: null,
      isPending: false,
      isError: false,
      status: "success" as const,
    };
  }

  return queryResult;
};
