import { Separator } from "@/components/ui/separator";
import { Eye } from "lucide-react";
import { format } from "date-fns";
import { useTranslation } from "react-i18next";
import { formatDurationToMinutes } from "@/lib/utils";
import type { VideoInfo } from "@/types/video.types";

function formatPublishDate(publishDate: string | number): string {
  const date = isNaN(Number(publishDate))
    ? new Date(publishDate)
    : new Date(Number(publishDate) * 1000);
  return format(date, "yyyy-MM-dd");
}

interface VideoMetadataProps {
  videoContent: VideoInfo;
}

export function VideoMetadata({ videoContent }: VideoMetadataProps) {
  const { t } = useTranslation();

  const title =
    videoContent.mash_title?.trim() || t("video_player.unknown_title");

  return (
    <div className="w-full mt-2 px-4 md:px-4 sm:px-4 xs:px-2">
      <h1 className="text-md sm:text-xl font-semibold tracking-tight">
        {title}
      </h1>
      <span className="flex items-center gap-2 font-semibold text-[#9E9E9E] mt-1.5">
        <span className="flex gap-1 items-center text-sm sm:text-xl">
          <Eye size={16} /> {videoContent?.play || 0}{" "}
          {t("video_player.views_count")}
        </span>
        {videoContent?.duration && (
          <>
            <Separator
              className="data-[orientation=vertical]:h-4 data-[orientation=vertical]:w-[2px]"
              orientation="vertical"
            />
            <span className="text-sm sm:text-xl">
              {formatDurationToMinutes(videoContent.duration, t)}
            </span>
          </>
        )}
        <Separator
          className="data-[orientation=vertical]:h-4 data-[orientation=vertical]:w-[2px]"
          orientation="vertical"
        />
        <span className="text-sm sm:text-xl">
          {videoContent?.publish_date
            ? formatPublishDate(videoContent.publish_date)
            : t("video_player.unknown_date")}
        </span>
      </span>
    </div>
  );
}
