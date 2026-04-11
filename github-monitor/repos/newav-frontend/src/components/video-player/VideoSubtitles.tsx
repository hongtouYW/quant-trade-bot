/**
 * VideoSubtitles Component
 *
 * Encapsulates all subtitle-related logic for the video player:
 * - Subtitle track management
 * - Language selection
 * - VTT processing with error correction
 *
 * This component is extracted to keep VideoPlayerCore clean and focused.
 */

import { useMemo, type RefObject } from "react";
import type { MediaPlayerInstance } from "@vidstack/react";
import { useTranslation } from "react-i18next";
import { useSubtitleTrackManager } from "@/hooks/video/useSubtitleTrackManager";
import { ProcessedSubtitleTrack } from "@/components/video-player/ProcessedSubtitleTrack";
import type { VideoInfo } from "@/types/video.types";

interface VideoSubtitlesProps {
  videoContent: VideoInfo;
  playerRef: RefObject<MediaPlayerInstance | null>;
}

export function VideoSubtitles({
  videoContent,
  playerRef,
}: VideoSubtitlesProps) {
  const { i18n, t } = useTranslation();
  const currentAppLanguage = i18n.language;

  // Get available subtitle tracks and determine which should be active
  const { zimuEntries, currentTrackIndex } = useSubtitleTrackManager({
    videoContent,
    currentAppLanguage,
    playerRef,
  });

  // Render subtitle tracks with error correction and caching
  const subtitleTracks = useMemo(() => {
    if (zimuEntries.length === 0) {
      return null;
    }

    // Only recreate when subtitle data changes, not when t function reference changes
    // Subtitle labels are static and don't need live translation updates
    return zimuEntries.map(([langCode, url], index) => (
      <ProcessedSubtitleTrack
        key={`${videoContent.id}-${langCode}`}
        langCode={langCode}
        url={url}
        videoId={videoContent.id ?? 0}
        default={index === currentTrackIndex}
        t={t}
      />
    ));
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [zimuEntries, currentTrackIndex, videoContent.id]);

  return <>{subtitleTracks}</>;
}
