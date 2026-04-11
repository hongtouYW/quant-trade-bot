import { useState, useEffect, useCallback } from "react";
import { useMutation, useQueryClient } from "@tanstack/react-query";
import { useAuth } from "@/hooks/auth/useAuth";
import { useAuthDialog } from "@/contexts/AuthDialogContext";
import { useVideoInfo } from "@/hooks/video/useVideoInfo";
import { useVideoUrl } from "@/hooks/video/useVideoUrl";
import { useVideoAccess } from "@/hooks/video/useVideoAccess";
import { collectVideo } from "@/services/video.service";
import { toast } from "sonner";
import { useTranslation } from "react-i18next";
import { useAuthAction } from "@/hooks/auth/useAuthAction";
import type { ApiResponse } from "@/types/api-response.ts";
import type { VideoInfo } from "@/types/video.types.ts";

export function useVideoPlayerState(
  videoId: string | undefined,
  initialVideoData?: ApiResponse<VideoInfo>,
) {
  const { isAuthenticated } = useAuth();
  const queryClient = useQueryClient();
  const { t } = useTranslation();
  const { executeWithAuth } = useAuthAction();
  const { showLogin } = useAuthDialog();

  // Local state
  const [localCollectState, setLocalCollectState] = useState<boolean | null>(
    null,
  );
  const [commentsExpanded, setCommentsExpanded] = useState(true);

  // Reset local collect state when video changes
  useEffect(() => {
    setLocalCollectState(null);
  }, [videoId]);

  // Data fetching
  const { data: videoInfo, isPending: isVideoInfoPending, isError } = useVideoInfo(videoId, initialVideoData);
  const videoContent = videoInfo?.data;
  const code = videoInfo?.code;

  // Check video access only if video info is successful (code === 1)
  // If videoInfo has an error code, we don't need to check access
  const shouldCheckAccess = code === 1;

  const {
    data: accessData,
    error: accessError,
    isPending: isAccessPending,
  } = useVideoAccess(
    shouldCheckAccess ? videoContent?.id : undefined,
    videoContent?.private
  );
  const hasAccess = accessData && !accessData.shouldShowOverlay;

  // Only fetch video URL if access is granted and video info is available
  const { data: videoUrl, error: videoUrlError } = useVideoUrl(
    videoContent?.id,
    code,
    hasAccess,
    videoContent?.private,
  );

  // Mutations
  const { mutate: mutateCollect, isPending: isCollecting } = useMutation({
    mutationFn: collectVideo,
    onSuccess: (response) => {
      setLocalCollectState(response.data.collect);
      queryClient.invalidateQueries({
        queryKey: ["videoInfo", videoId],
      });

      // Show toast notification based on collect state
      if (response.data.collect) {
        toast(t("toast.video_collected"));
      } else {
        toast(t("toast.video_uncollected"), {
          style: {
            background: "#E03C6C",
          },
        });
      }
    },
    onError: (error) => {
      console.error("Collect operation failed:", error);
      setLocalCollectState(null);
      toast(t("toast.collect_error"));
    },
  });

  // Derived state
  const shouldShowAuthOverlay = Boolean(
    accessData?.shouldShowOverlay || accessError,
  );
  const overlayErrorType = accessError
    ? "server"
    : accessData?.errorType || undefined;
  const overlayErrorMessage = accessError?.message || accessData?.errorMessage;
  const currentCollectState =
    localCollectState !== null
      ? localCollectState
      : videoContent?.is_collect === 1;

  // Handlers - memoized to prevent unnecessary rerenders
  const handleLoginClick = useCallback(() => {
    showLogin();
  }, [showLogin]);

  const handlePurchaseClick = useCallback(() => {
    executeWithAuth(() => {
      if (videoContent?.id) {
        // Trigger purchase dialog - similar to voucher dialog pattern
        // This could open a purchase dialog or navigate to purchase page
        // You can add purchase dialog logic here
      }
    });
  }, [executeWithAuth, videoContent?.id]);

  const handleCollect = useCallback(() => {
    executeWithAuth(() => {
      if (videoContent?.id && !isCollecting) {
        mutateCollect({
          vid: Number(videoContent.id),
        });
      }
    });
  }, [executeWithAuth, videoContent?.id, isCollecting, mutateCollect]);

  const handleToggleComments = useCallback(() => {
    setCommentsExpanded(!commentsExpanded);
  }, [commentsExpanded]);

  // Log access errors for debugging
  if (accessError) {
    console.error("Video access check failed:", accessError.message);
  }

  return {
    // Data
    videoInfo,
    videoContent,
    videoUrl,
    videoUrlError,

    // Loading states
    isPending: isVideoInfoPending || isAccessPending,
    isVideoInfoPending,
    isAccessPending,
    isError,
    isCollecting,

    // Auth state
    isAuthenticated,
    shouldShowAuthOverlay,
    overlayErrorType,
    overlayErrorMessage,

    // UI state
    commentsExpanded,
    currentCollectState,

    // Handlers
    handleLoginClick,
    handlePurchaseClick,
    handleCollect,
    handleToggleComments,
  };
}
