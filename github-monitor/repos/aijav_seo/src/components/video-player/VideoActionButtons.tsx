import { Button } from "@/components/ui/button";
import { Bookmark, Ticket } from "lucide-react";
import { useTranslation } from "react-i18next";
import { ShareDialog } from "@/components/ShareDialog";
import { FeedbackDialog } from "@/components/FeedbackDialog";
import { cn } from "@/lib/utils";
import { useAuthAction } from "@/hooks/auth/useAuthAction";
// import { useUser } from "@/contexts/UserContext";
import type { VideoInfo } from "@/types/video.types";

interface VideoActionButtonsProps {
  videoContent: VideoInfo;
  currentCollectState: boolean;
  isCollecting: boolean;
  onCollect: () => void;
  onVoucherClick?: () => void;
  className?: string;
}

export function VideoActionButtons({
  videoContent,
  currentCollectState,
  isCollecting,
  onCollect,
  onVoucherClick,
  className,
}: VideoActionButtonsProps) {
  const { t } = useTranslation();
  const { executeWithAuth } = useAuthAction();
  // const { user } = useUser();

  // Hide voucher button when video is free or purchased
  const isVideoFree = videoContent?.private === 0;
  const isVideoPurchased = videoContent?.is_purchase === 1;
  const isVideoInSeries = videoContent?.private === 3;
  const shouldHideVoucherButton =
    isVideoFree || isVideoPurchased || isVideoInSeries;

  return (
    <div
      className={cn(
        "inline-flex items-center justify-end gap-1 sm:gap-2 flex-shrink-0",
        className,
      )}
    >
      <Button
        className="rounded-full size-9 sm:w-fit text-sm px-2! sm:h-9 sm:px-4! font-semibold bg-[#F4A8FF]/20 text-[#EC67FF] border-[#EC67FF] hover:text-[#EC67FF]"
        variant="outline"
        onClick={() => executeWithAuth(onCollect)}
        disabled={isCollecting}
      >
        <Bookmark
          className={cn(
            currentCollectState && "fill-[#EC67FF]",
            isCollecting && "animate-pulse",
          )}
        />
        <span className="hidden sm:inline">
          {isCollecting
            ? t("video_player.loading")
            : currentCollectState
              ? t("video_player.collected")
              : t("video_player.collect")}
        </span>
      </Button>
      {!shouldHideVoucherButton && (
        <Button
          className="rounded-full size-9 sm:w-fit font-semibold px-2! sm:h-9 sm:px-4! bg-[#F4A8FF]/20 text-[#EC67FF] border-[#EC67FF] hover:text-[#EC67FF]"
          variant="outline"
          onClick={() => onVoucherClick && executeWithAuth(onVoucherClick)}
        >
          <Ticket />
          <span className="hidden sm:inline">
            {t("video_player.use_voucher")}
          </span>
        </Button>
      )}
      <ShareDialog />
      <FeedbackDialog videoId={videoContent?.id} />
    </div>
  );
}
