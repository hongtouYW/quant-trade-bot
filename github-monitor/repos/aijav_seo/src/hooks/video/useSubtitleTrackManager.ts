import { useEffect, useState, useMemo, type RefObject } from "react";
import type { MediaPlayerInstance } from "@vidstack/react";
import { getDefaultSubtitleTrack } from "@/utils/subtitle-utils";
import type { VideoInfo } from "@/types/video.types";

interface UseSubtitleTrackManagerProps {
  videoContent: VideoInfo;
  currentAppLanguage: string;
  playerRef: RefObject<MediaPlayerInstance | null>;
}

export function useSubtitleTrackManager({
  videoContent,
  currentAppLanguage,
  playerRef,
}: UseSubtitleTrackManagerProps) {
  const [captionsEnabled, setCaptionsEnabled] = useState(false);
  const [tracksReady, setTracksReady] = useState(false);
  const [defaultTrackEnabled, setDefaultTrackEnabled] = useState(false);

  // Memoize the zimu entries to avoid recreating on every render
  const zimuEntries = useMemo(() => {
    if (!videoContent?.zimu || typeof videoContent.zimu !== "object") {
      return [];
    }
    return Object.entries(videoContent.zimu).filter(
      ([langCode]) => langCode === "en" || langCode === "zh",
    );
  }, [videoContent?.zimu]);

  // Memoize current track index to avoid unnecessary switching
  const currentTrackIndex = useMemo(
    () => getDefaultSubtitleTrack(zimuEntries, currentAppLanguage),
    [zimuEntries, currentAppLanguage],
  );

  // Track caption state changes
  useEffect(() => {
    if (!playerRef.current) return;

    const player = playerRef.current;

    const handleTextTracksChange = () => {
      const textTracks = player.textTracks;
      let isShowing = false;

      for (let i = 0; i < textTracks.length; i++) {
        const track = textTracks[i];
        if (track && track.kind === "subtitles" && track.mode === "showing") {
          isShowing = true;
          break;
        }
      }

      setCaptionsEnabled(isShowing);
    };

    const handleCanPlay = () => {
      setTracksReady(true);
      handleTextTracksChange();
    };

    // Listen to player events
    player.addEventListener("can-play", handleCanPlay);
    player.textTracks.addEventListener("mode-change", handleTextTracksChange);

    return () => {
      player.removeEventListener("can-play", handleCanPlay);
      player.textTracks.removeEventListener(
        "mode-change",
        handleTextTracksChange,
      );
    };
  }, []);

  // Enable default subtitle track when video is ready
  useEffect(() => {
    if (!playerRef.current || !tracksReady || defaultTrackEnabled) {
      return;
    }

    const player = playerRef.current;

    // Check if we have valid tracks
    if (zimuEntries.length === 0 || currentTrackIndex === -1) {
      return;
    }

    // Enable the default subtitle track
    let subtitleIndex = 0;
    for (let i = 0; i < player.textTracks.length; i++) {
      const track = player.textTracks[i];
      if (track && track.kind === "subtitles") {
        track.mode = subtitleIndex === currentTrackIndex ? "showing" : "disabled";
        subtitleIndex++;
      }
    }

    setDefaultTrackEnabled(true);
  }, [tracksReady, currentTrackIndex, zimuEntries.length, defaultTrackEnabled]);

  // Handle language switching when app language changes
  useEffect(() => {
    // Skip if not ready or no tracks
    if (!playerRef.current || !tracksReady || zimuEntries.length === 0) {
      return;
    }

    const player = playerRef.current;

    // Validate track index
    if (currentTrackIndex === -1) {
      return;
    }

    // Switch subtitle tracks - this will work regardless of whether captions are currently enabled
    let subtitleIndex = 0;
    for (let i = 0; i < player.textTracks.length; i++) {
      const track = player.textTracks[i];
      if (track && track.kind === "subtitles") {
        track.mode = subtitleIndex === currentTrackIndex ? "showing" : "disabled";
        subtitleIndex++;
      }
    }
  }, [currentTrackIndex, tracksReady, zimuEntries.length, currentAppLanguage]);

  return {
    captionsEnabled,
    tracksReady,
    zimuEntries,
    currentTrackIndex,
  };
}
