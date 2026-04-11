/**
 * VideoSubtitles Component
 *
 * Encapsulates all subtitle-related logic for the video player:
 * - Subtitle track management
 * - Language selection
 *
 * This component is extracted to keep VideoPlayerCore clean and focused.
 */

import { useMemo, type RefObject } from "react";
import type { MediaPlayerInstance } from "@vidstack/react";
import { Track } from "@vidstack/react";
import { useTranslation } from "react-i18next";
import { useSubtitleTrackManager } from "@/hooks/video/useSubtitleTrackManager";
import { getSubtitleLabel } from "@/utils/subtitle-utils";
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

  // Render subtitle tracks
  const subtitleTracks = useMemo(() => {
    if (zimuEntries.length === 0) {
      return null;
    }

    return zimuEntries.map(([langCode, url], index) => (
      <Track
        key={`${videoContent.id}-${langCode}`}
        src={url}
        kind="subtitles"
        label={getSubtitleLabel(langCode, t)}
        type="vtt"
        default={index === currentTrackIndex}
      />
    ));
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [zimuEntries, currentTrackIndex, videoContent.id]);

  return <>{subtitleTracks}</>;
}
