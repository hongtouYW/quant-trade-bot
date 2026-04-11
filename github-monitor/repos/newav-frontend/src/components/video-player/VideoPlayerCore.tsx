import {
  MediaPlayer,
  type MediaPlayerInstance,
  MediaProvider,
  Poster,
  SeekButton,
} from "@vidstack/react";
import {
  defaultLayoutIcons,
  DefaultVideoLayout,
} from "@vidstack/react/player/layouts/default";
import { VideoAuthOverlay } from "@/components/video-auth-overlay";
import { useTranslation } from "react-i18next";
import { useMemo, useRef, useState, useEffect } from "react";
import { getVidstackTranslations } from "@/utils/subtitle-utils";
import { useBase64Image } from "@/hooks/useBase64Image";
import { useVTTBlobUrl } from "@/utils/vtt-generator";
import { VideoSubtitles } from "@/components/video-player/VideoSubtitles";
import { parseDurationToSeconds } from "@/utils/duration-parser";
import type { VideoInfo } from "@/types/video.types";
import { FastForward } from "lucide-react";

interface VideoPlayerCoreProps {
  videoUrl: string | null;
  videoContent: VideoInfo;
  shouldShowAuthOverlay: boolean;
  onLoginClick: () => void;
  overlayErrorType?:
    | "auth"
    | "purchase"
    | "purchase-or-vip"
    | "series"
    | "server"
    | "vip-expired";
  overlayErrorMessage?: string;
  onPurchaseClick?: () => void;
  vipVideoDuration?: string;
}

export function VideoPlayerCore({
  videoUrl,
  videoContent,
  shouldShowAuthOverlay,
  onLoginClick,
  overlayErrorType,
  overlayErrorMessage,
  onPurchaseClick,
  vipVideoDuration,
}: VideoPlayerCoreProps) {
  const { t } = useTranslation();
  const playerRef = useRef<MediaPlayerInstance>(null);
  const [videoUrlReady, setVideoUrl] = useState("");
  const [isWaitingToPlay, setIsWaitingToPlay] = useState(true);
  const [isTransitioning, setIsTransitioning] = useState(false);
  const previousVideoId = useRef<number | null>(null);

  // Handle video transitions and loading states
  useEffect(() => {
    const currentVideoId = videoContent.id ?? null;

    // Detect video change
    if (
      previousVideoId.current !== null &&
      previousVideoId.current !== currentVideoId
    ) {
      setIsTransitioning(true);
      setVideoUrl("");
      setIsWaitingToPlay(true);
    }

    previousVideoId.current = currentVideoId;

    if (videoUrl) {
      setIsWaitingToPlay(false);
      setIsTransitioning(false);
      setVideoUrl(videoUrl);
    }
  }, [videoUrl, videoContent.id]);

  // Get transformed poster image URL
  // Include video ID to ensure proper cache invalidation when switching videos
  const originalPosterUrl = videoContent.preview || videoContent.thumb || "";
  const { data: transformedPosterUrl } = useBase64Image({
    originalUrl: originalPosterUrl,
    enabled: !!originalPosterUrl && !!videoContent.id,
  });

  const vidstackTranslations = useMemo(() => getVidstackTranslations(t), [t]);
  const videoDurationSeconds = useMemo(
    () => parseDurationToSeconds(videoContent.duration || "0"),
    [videoContent.duration],
  );

  // Memoize VTT options to prevent unnecessary blob URL recreations
  const vttOptions = useMemo(
    () =>
      videoContent.panorama
        ? {
            spriteUrl: videoContent.panorama,
            videoDurationSeconds,
          }
        : null,
    [videoContent.panorama, videoDurationSeconds],
  );

  // Generate VTT thumbnails if panorama sprite is available
  const thumbnailsVttUrl = useVTTBlobUrl(vttOptions);

  return (
    <MediaPlayer
      key={videoContent.id || "video-player"}
      ref={playerRef}
      aspectRatio="16/9"
      className={`player ${isWaitingToPlay || isTransitioning ? "is-loading" : ""}`}
      load="play"
      src={videoUrlReady || undefined}
      viewType="video"
      storage={`video-${videoContent.id || "default"}`}
      playsInline
    >
      <MediaProvider />
      <DefaultVideoLayout
        slots={{
          beforePlayButton: (
            <SeekButton className="vds-play-button vds-button" seconds={-10}>
              <FastForward className="vds-icon rotate-180" />
            </SeekButton>
          ),
          afterPlayButton: (
            <SeekButton className="vds-play-button vds-button" seconds={10}>
              <FastForward className="vds-icon" />
            </SeekButton>
          ),
        }}
        seekStep={10}
        noAudioGain={true}
        colorScheme="system"
        icons={defaultLayoutIcons}
        translations={vidstackTranslations}
        thumbnails={thumbnailsVttUrl || undefined}
      />
      <Poster
        src={
          isTransitioning
            ? originalPosterUrl || ""
            : transformedPosterUrl || originalPosterUrl || ""
        }
        className="vds-poster custom-poster blur-none"
        alt="Video poster image"
      />

      {/* Show loading indicator when waiting for video URL or transitioning */}
      {(isWaitingToPlay || isTransitioning) && !shouldShowAuthOverlay && (
        <div className="absolute inset-0 flex items-center justify-center bg-black/40 rounded-lg pointer-events-none z-50">
          <div className="flex flex-col items-center gap-3">
            <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-white"></div>
            <p className="text-white text-sm font-medium">
              {t("video_player.loading")}
            </p>
          </div>
        </div>
      )}

      {/* Subtitle tracks with error correction and caching */}
      <VideoSubtitles videoContent={videoContent} playerRef={playerRef} />

      {/* Authentication/Purchase overlay */}
      {shouldShowAuthOverlay && (
        <VideoAuthOverlay
          onLoginClick={onLoginClick}
          errorType={overlayErrorType || "auth"}
          errorMessage={overlayErrorMessage}
          onPurchaseClick={onPurchaseClick}
          videoGroup={videoContent.video_group || undefined}
          vipVideoDuration={vipVideoDuration}
        />
      )}
    </MediaPlayer>
  );
}
